# Klinika — Sistem i Menaxhimit të Klinikave Mjekësore

Aplikacion ueb i ndërtuar tërësisht me **PHP, MySQL, HTML, CSS dhe JavaScript** (pa framework-e të jashtme), i zgjeruar me module të plota klinike dhe administrative:

## Modulet kryesore
1. **Paneli i Analitikave (`index.php`)**: Treguesit KPI në kohë reale, ngarkesa ditore për 7 ditët e ardhshme, shpërndarja e statuseve të termineve, agjenda e ditës dhe faturat në pritje.
2. **Kartela Mjekësore EHR (`kartela/`)**: Regjistrimi i diagnozave (me kod ICD-10), shenjave vitale (presioni i gjakut, pulsi, temperatura, pesha), anamnezës, recetave dhe printimi i raportit klinik.
3. **Terminet & Kalendari Javor (`terminet/`)**: Pamje tabelare dhe kalendar 7-ditor, kontroll konflikti të orarit, check-in nga recepsioni (`Planifikuar` → `Konfirmuar` → `Në pritje` → `Përfunduar`).
4. **Faturimi & Financat (`faturimi/`)**: Katalogu i shërbimeve (`sherbimet_klinike`), krijimi i faturave me zëra dinamikë, pagesa të plota ose të pjesshme (`cash`, `kartë`, `transfertë`, `sigurim`) dhe faturë e printueshme.
5. **Dosja e Pacientit & Ekipi Mjekësor (`pacientet/`, `mjeket/`)**: Të dhëna demografike, grupi i gjakut, alergjitë, kontakti emergjent, orari i mjekut dhe tarifa bazike.
6. **Rolet dhe Siguria (`includes/bootstrap.php`, `admin/`)**:
   - `admin`: qasje e plotë në të gjitha modulet, analitika, përdoruesit dhe regjistrin e kyçjeve.
   - `mjek`: orari personal, kartelat EHR dhe dosjet klinike të pacientëve të vet (pa qasje në fatura/admin).
   - `recepsionist`: pacientët, terminet, check-in dhe faturimi (pa qasje në shënimet konfidenciale EHR).
   - `pacient`: portali personal i pacientit (rezervim/anulim terminesh, histori EHR vetëm-lexim, fatura vetëm-lexim).

## Struktura e dosjeve
- `config/` — Konfigurimi i aplikacionit dhe lidhja PDO me MySQL
- `includes/` — Sesionet, siguria CSRF, kontrolli RBAC, ndihmësit dhe paraqitja
- `database/` — Skema e plotë (`klinika_db.sql`), të dhënat demo (`seed_demo.sql`) dhe migrimet `001`–`005`
- `admin/` — Menaxhimi i llogarive, roleve dhe regjistrit të hyrjeve
- `panels/` — Panelet e dedikuara për Mjekun, Recepsionistin dhe Pacientin
- `pacientet/`, `mjeket/`, `terminet/`, `kartela/`, `faturimi/`, `api/` — Modulet funksionale
- `assets/css/`, `assets/js/`, `assets/img/` — Stilet, skriptet dhe imazhet të ndara rreptësisht (zero CSS/JS inline)

## Udhëzime instalimi (XAMPP / Apache + MySQL)
1. Kopjoni dosjen `menaxhimi-klinikave` brenda `htdocs/` të XAMPP (ose përdorni rrënjën e serverit Apache).
2. Hapni **phpMyAdmin** (`http://localhost/phpmyadmin`).
3. Për instalim të ri: importoni `database/klinika_db.sql` dhe më pas `database/seed_demo.sql`.
4. Për databazë ekzistuese nga versioni i mëparshëm: ekzekutoni migrimin `database/migrations/005_klinika_im_modules.sql`.
5. Përditësoni kredencialet e MySQL te `config/database.php` nëse keni fjalëkalim për `root`.
6. Hapni aplikacionin në shfletues: `http://localhost/menaxhimi-klinikave/setup.php` për të krijuar llogarinë e parë Admin.
