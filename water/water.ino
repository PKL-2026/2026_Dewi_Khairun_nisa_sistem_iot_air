#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// ==========================================
// KONFIGURASI WIFI & SERVER CLOUD (HTTPS)
// ==========================================
const char* ssid = "wifi-iot";
const char* password = "password-iot";

// Alamat API Server cPanel Hosting (HTTPS)
const char* serverName = "https://localhost.scode.web.id/2026-dewi-khoirun-nisa-iot-air/api/post_data.php";

// ==========================================
// KONFIGURASI PIN & SENSOR
// ==========================================
LiquidCrystal_I2C lcd(0x27, 16, 2);

const int pinSuhu = 13; // DS18B20 DATA + resistor pull-up 3.3k ke 3.3V
OneWire oneWire(pinSuhu);
DallasTemperature sensorSuhu(&oneWire);

const int pinPH = 34;
const int pinTurbidity = 35;

// ===== KALIBRASI TURBIDITY =====
const float TURB_V_JERNIH = 2.90;  // tegangan (V) saat air jernih (0 NTU)
const float TURB_V_KERUH  = 0.50;  // tegangan (V) saat sangat keruh (3000 NTU)
// ===============================

float suhuAir = 0.0;
bool suhuValid = false;
float phAir = 0.0;
float turbidityAir = 0.0;

// Status dari server (STANDBY atau MENGUKUR)
String serverState = "STANDBY";
bool isFirstMeasure = true;

// ===== FILTER pH NON-BLOCKING (ROLLING BUFFER) =====
const int PH_WINDOW_SIZE = 15;
float phVoltageSamples[PH_WINDOW_SIZE];
int phSampleIndex = 0;
bool phBufferFilled = false;
unsigned long lastPHSampleTime = 0;
const unsigned long phSampleInterval = 50; // Sampel tiap 50ms (0ms delay)

void samplePHNonBlocking() {
  int raw = analogRead(pinPH);
  float v = raw * (3.3 / 4095.0);
  phVoltageSamples[phSampleIndex] = v;
  phSampleIndex = (phSampleIndex + 1) % PH_WINDOW_SIZE;
  if (phSampleIndex == 0) phBufferFilled = true;
}
// ===================================================

void checkHardware() {
  Serial.println("\n===============================");
  Serial.println("MEMULAI DIAGNOSTIK HARDWARE...");
  
  // 1. Cek Sensor Suhu (DS18B20)
  sensorSuhu.begin();
  int countSuhu = sensorSuhu.getDeviceCount();
  if (countSuhu > 0) {
    Serial.println("[OK] Sensor Suhu terdeteksi.");
  } else {
    Serial.println("[ERROR] Sensor Suhu TIDAK terdeteksi! Cek kabel GPIO13 / resistor pull-up (2.2k-4.7k).");
  }
  sensorSuhu.setResolution(12);      // resolusi 0.0625 C
  sensorSuhu.requestTemperatures();  // bacaan pertama dibuang (sering -127)
  
  // 2. Cek LCD I2C
  Wire.begin();
  Wire.beginTransmission(0x27);
  byte error = Wire.endTransmission();
  if (error == 0) {
    Serial.println("[OK] LCD I2C terdeteksi pada alamat (0x27).");
  } else {
    Wire.beginTransmission(0x3F);
    if(Wire.endTransmission() == 0) {
       Serial.println("[OK] LCD I2C terdeteksi pada alamat (0x3F).");
    } else {
       Serial.println("[ERROR] LCD I2C TIDAK terdeteksi! Cek kabel SDA & SCL.");
    }
  }
  
  Serial.println("===============================\n");
}

void setup() {
  Serial.begin(115200);
  
  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print("Kualitas Air IoT");
  lcd.setCursor(0, 1);
  lcd.print("Menghubungkan...");
  
  // Jalankan Self-Test
  checkHardware();
  
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  
  Serial.println("\nWiFi connected!");
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("WiFi Connected!");
  delay(2000);
}

void readSensors() {
  sensorSuhu.requestTemperatures();
  float t = sensorSuhu.getTempCByIndex(0);
  if (t == DEVICE_DISCONNECTED_C) {          // -127, coba sekali lagi
    delay(100);
    sensorSuhu.requestTemperatures();
    t = sensorSuhu.getTempCByIndex(0);
  }
  if (t != DEVICE_DISCONNECTED_C) {
    suhuAir = t;
    suhuValid = true;
  } else {
    suhuValid = false;
    // Serial.println("[WARN] Suhu gagal dibaca, pakai nilai terakhir.");
  }
  

  
  // Hitung rata-rata tegangan pH dari Rolling Buffer (Anti-Noise & 0ms delay)
  int sampleCount = phBufferFilled ? PH_WINDOW_SIZE : (phSampleIndex > 0 ? phSampleIndex : 1);
  float sumVolt = 0.0;
  for (int i = 0; i < sampleCount; i++) {
    sumVolt += phVoltageSamples[i];
  }
  float voltagePH = (sampleCount > 0) ? (sumVolt / sampleCount) : (analogRead(pinPH) * (3.3 / 4095.0));
  
  // Rumus Kalibrasi Kustom (Terlindungi di 3.3V VCC)
  phAir = (-4.085 * voltagePH) + 17.48; 
  
  // Pengaman Software: Pastikan pH tidak menampilkan minus/ngawur
  if(phAir < 0.0) phAir = 0.0;
  if(phAir > 14.0) phAir = 14.0;
  int nilaiAnalogTurbidity = analogRead(pinTurbidity);
  float voltageTurbidity = nilaiAnalogTurbidity * (3.3 / 4095.0);
  
  // Hitung turbidity pakai map float
  if (voltageTurbidity >= TURB_V_JERNIH) {
    turbidityAir = 0;
  } else if (voltageTurbidity <= TURB_V_KERUH) {
    turbidityAir = 3000;
  } else {
    // Map tegangan ke NTU
    turbidityAir = (TURB_V_JERNIH - voltageTurbidity) / (TURB_V_JERNIH - TURB_V_KERUH) * 3000.0;
  }
}

void updateLCD() {
  lcd.clear();
  
  if (serverState == "STANDBY") {
    lcd.setCursor(0, 0);
    lcd.print("Kualitas Air IoT");
    lcd.setCursor(0, 1);
    lcd.print("Mode: Standby");
  } 
  else {
    // Mode MENGUKUR
    if (isFirstMeasure) {
      lcd.setCursor(0, 0);
      lcd.print("Memuat GPS...");
      lcd.setCursor(0, 1);
      lcd.print("Memulai ukur...");
      delay(2000); 
      isFirstMeasure = false;
      lcd.clear();
    }
    
    lcd.setCursor(0, 0);
    lcd.print("S:");
    if (suhuValid) lcd.print(suhuAir, 1); else lcd.print("ERR");
    lcd.print(" pH:"); lcd.print(phAir, 1);
    
    lcd.setCursor(0, 1);
    lcd.print("Turb:"); lcd.print((int)turbidityAir); lcd.print(" NTU");
  }
}

void sendDataToServer() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    bool isHttps = (String(serverName).indexOf("https://") == 0);

    if (isHttps) {
      WiFiClientSecure client;
      client.setInsecure(); // Bypass validasi SSL jika HTTPS
      http.begin(client, serverName);
    } else {
      WiFiClient client;
      http.begin(client, serverName);
    }
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    String httpRequestData = "suhu=" + String(suhuAir) + "&ph=" + String(phAir) + "&turbidity=" + String(turbidityAir);
    
    int httpResponseCode = http.POST(httpRequestData);
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      // Serial.println("Response: " + response);
      
      String newState = serverState;
      if (response.indexOf("CMD:MENGUKUR") != -1) {
        newState = "MENGUKUR";
      } else if (response.indexOf("CMD:STANDBY") != -1) {
        newState = "STANDBY";
      }

      // Jika berubah dari standby ke mengukur, trigger isFirstMeasure agar pesan muncul
      if (serverState == "STANDBY" && newState == "MENGUKUR") {
        isFirstMeasure = true;
      }
      serverState = newState;
    } else {
      // Serial.print("Error code: ");
      // Serial.println(httpResponseCode);
    }
    http.end();
  } else {
    // Serial.println("WiFi Disconnected");
  }
}

unsigned long lastUpdate = 0;
const long interval = 3000; // Kirim data setiap 3 detik supaya lebih responsif

void loop() {
  unsigned long currentMillis = millis();
  
  // Sampling pH non-blocking setiap 50ms tanpa delay
  if (currentMillis - lastPHSampleTime >= phSampleInterval) {
    lastPHSampleTime = currentMillis;
    samplePHNonBlocking();
  }
  
  if (currentMillis - lastUpdate >= interval) {
    lastUpdate = currentMillis;
    
    readSensors();
    updateLCD();
    sendDataToServer();
    
    // Serial.printf("Suhu: %.2f | pH: %.2f | Turb: %.0f\n", suhuAir, phAir, turbidityAir);
  }
}
