kalau mau ubah

- untuk database webnya bisa langsung export/ekspor database_2026_dewi_khairunnisa_iot_air ke dalam phpmyadmin milikmu

- bagian water.ino , perhatikan pada baris ke 15 (const char* serverName = "http://192.168.18.35:8081/api/post_data.php"; //on line 15) perhatikan, hanya ubah bagian ip ini yang sesuai dengan ip laptopmu (ipconfig) "http://192.168.**.**:****/api/post_data.php" 

- jika mau dihosting mungkin nanti dalam water.ino bisa menjadi "http://2026-Dewi-Khairunnisa-sistem-iot-air/api/post_data.php" (tergantung nama yang ada dihosting, dan jangan lupa cek bagian #include <...> pada baris awal jika masih belum mau connect antara alat dengan web nya 

- 
- 
- 
- 
- 
- 

_
_
_
