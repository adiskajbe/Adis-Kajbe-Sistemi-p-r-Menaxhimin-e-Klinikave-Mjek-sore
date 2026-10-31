# Sistemi për Menaxhimin e Klinikave Mjekësore

Ky është një aplikacion demonstrues PHP/MySQL për menaxhimin e pacientëve, mjekëve dhe termineve. Ai përfshin CRUD-in për pacientë e mjekë dhe regjistrimin, listimin, ndryshimin, anulimin dhe fshirjen e termineve.

Aplikacioni është për mësim dhe demonstrim. Mos ruani të dhëna reale mjekësore.

## Kërkesat

- XAMPP me Apache, PHP dhe MySQL/MariaDB
- PHP 7.4 ose më i ri me PDO MySQL
- Shfletues modern

## Nisja me XAMPP

1. Vendoseni dosjen e projektit (atë që përmban `index.php`, `config/` dhe `database/`) te `C:\xampp\htdocs\menaxhimi-klinikave`.
2. Hapni XAMPP Control Panel dhe nisni Apache dhe MySQL.
3. Hapni phpMyAdmin në `http://localhost/phpmyadmin`.
4. Importoni skedarin `database/klinika_db.sql`.
5. Nëse po përdorni databazën e vjetër, zgjidhni `klinika_db` në phpMyAdmin dhe ekzekutoni një herë `database/migrations/001_add_mjeku_email.sql`.
6. Hapni `config/database.php`. Vlerat fillestare përputhen me konfigurimin lokal të zakonshëm të XAMPP (`root`, pa fjalëkalim). Përdorni variablat `DB_HOST`, `DB_NAME`, `DB_USER` dhe `DB_PASS` nëse konfigurimi juaj ndryshon.
7. Hapni `http://localhost/menaxhimi-klinikave/` (përdorni emrin e dosjes që keni vendosur).

Për demonstrim mund të importoni edhe `database/seed_demo.sql`; ai përdor vetëm të dhëna sintetike dhe mund të ekzekutohet përsëri pa dublikuar mostrën.

## Funksionalitetet

- Pacientët: regjistrim, listim, kërkim, ndryshim dhe fshirje. Fshirja bllokohet kur pacienti ka termine.
- Mjekët: regjistrim, listim, kërkim, ndryshim dhe fshirje. Fshirja bllokohet kur mjeku ka termine.
- Terminet: regjistrim, listim me pacientin dhe mjekun, ndryshim, anulim përmes statusit dhe fshirje e përhershme.
- Termini i planifikuar nuk mund të caktohet në të kaluarën dhe mjeku nuk mund të ketë dy termine të planifikuara në të njëjtën orë.
- Aplikacioni renderon HTML nga PHP dhe nuk ekspozon REST/JSON API.

## Skenarët e testimit manual

| ID | Skenari | Rezultati i pritur |
|---|---|---|
| P01 | Regjistro pacient të vlefshëm | Ruhet dhe shfaqet në listë |
| P02 | Lër bosh emrin ose telefonin | Validimi refuzon ruajtjen |
| P03 | Jep email të pavlefshëm | Forma refuzohet |
| P04 | Kërko dhe ndrysho pacientin | Gjendet; ndryshimi ruhet pas rifreskimit |
| P05 | Fshi pacient pa termin | Fshirja kryhet |
| P06 | Fshi pacient me termin të lidhur | Fshirja bllokohet |
| M01 | Krijo, kërko dhe ndrysho mjek | Të dhënat ruhen e shfaqen |
| M02 | Fshi mjek pa termin / me termin | Fshihet pa lidhje; bllokohet me lidhje |
| T01 | Cakto termin me pacient dhe mjek ekzistues | Termini ruhet dhe shfaqet |
| T02 | Provo datë të kaluar | Validimi refuzon termin e planifikuar |
| T03 | Cakto dy termine për të njëjtin mjek në të njëjtën orë | Termini i dytë refuzohet |
| T04 | Ndrysho, anulo dhe fshi termin | Përditësimi, anulimi dhe fshirja funksionojnë |

## Rezultatet reale të testimit

Testimi u krye më 28.10.2026 kundrejt kopjes origjinale të projektit, me PHP 8.2.12, MariaDB 10.4.32 dhe databazë të veçantë testimi me të dhëna sintetike.

| Fusha | Rezultati | Verifikimi |
|---|---|---|
| Pacientët (P01–P06) | PASS | Regjistrimi, validimi i fushave dhe email-it, kërkimi sipas emrit/mbiemrit, ruajtja e ndryshimeve pas rifreskimit, fshirja pa termin dhe bllokimi i fshirjes me termin |
| Mjekët (M01–M02) | PASS | CRUD-i, kërkimi dhe bllokimi i fshirjes kur ka termine të lidhura |
| Terminet (T01–T04) | PASS | Krijimi me pacient/mjek ekzistues, refuzimi i datës së kaluar, parandalimi i konfliktit të orarit, ndryshimi, anulimi dhe fshirja |
| Kërkesat PHP | PASS | `php -l` nuk raportoi gabime sintaksore në asnjë skedar PHP të projektit |
| Testet funksionale HTTP/DB | PASS | 32 nga 32 kontrolle të automatizuara kaluan kundrejt kopjes origjinale |
| Migrimi i email-it të mjekut | PASS | U provua dy herë në skemë të vjetër; kolona ekziston dhe migrimi mund të përsëritet pa gabim |
| Skema dhe të dhënat demo | PASS | Importi i skemës u verifikua; seed-i u ekzekutua dy herë pa krijuar dublikatë |

Të gjitha kontrollet u kryen me të dhëna sintetike. Testet e aplikacionit përdorën databazë të izoluar nga `klinika_db`.

## Struktura

```text
menaxhimi-klinikave/
├── index.php
├── pacientet/
│   ├── lista.php
│   ├── shto.php
│   ├── ndrysho.php
│   └── fshi.php
├── mjeket/
│   ├── lista.php
│   ├── shto.php
│   ├── ndrysho.php
│   └── fshi.php
├── terminet/
│   ├── lista.php
│   ├── shto.php
│   ├── ndrysho.php
│   ├── anulo.php
│   └── fshi.php
├── includes/
│   ├── bootstrap.php
│   ├── database_notice.php
│   ├── footer.php
│   ├── functions.php
│   └── header.php
├── config/
│   ├── app.php
│   └── database.php
├── assets/
│   ├── css/style.css
│   └── js/script.js
└── database/
    ├── klinika_db.sql
    ├── seed_demo.sql
    └── migrations/001_add_mjeku_email.sql
```

Format validohen në server. Veprimet përdorin PDO prepared statements dhe token CSRF; output-i HTML kalohet përmes funksionit `h()`.
