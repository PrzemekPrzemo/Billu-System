# BiLLU Financial Solutions - Lista funkcjonalnosci

## Dla dzialu marketingu i sprzedazy

---

## 1. OPIS PRODUKTU

**BiLLU Financial Solutions** to nowoczesna platforma SaaS do zarzadzania obiegiem faktur zakupowych
pomiedzy biurami ksiegowymi a ich klientami. System umozliwia import, weryfikacje,
akceptacje i raportowanie faktur z pelna integracja z Krajowym Systemem e-Faktur (KSeF).

**Model licencyjny:** Subskrypcja miesięczna lub roczna (minimum 6 miesiecy).

---

## 2. GLOWNE FUNKCJONALNOSCI

### 2.1 Trzy panele uzytkownikow

| Panel | Uzytkownik | Opis |
|-------|-----------|------|
| **Panel Administratora** | Operator systemu | Pelne zarzadzanie systemem, klientami, biurami, ustawieniami |
| **Panel Biura Ksiegowego** | Biuro rachunkowe | Import faktur, zarzadzanie klientami, raporty |
| **Panel Klienta** | Firma (nabywca) | Weryfikacja faktur, akceptacja/odrzucenie, raporty, KSeF |

---

### 2.2 Import faktur

- **Import z plikow Excel (XLSX/XLS)** - 15-kolumnowy szablon z automatycznym rozpoznawaniem
- **Import z plikow CSV/TXT** - automatyczne wykrywanie separatora (tabulator, srednik)
- **Import bezposredni z KSeF** - pobieranie faktur z Krajowego Systemu e-Faktur przez API
- **Import masowy klientow** - dodawanie wielu klientow jednoczesnie z pliku CSV
- **Szablon importu Excel do pobrania** - gotowy plik XLSX z naglowkami, przykladem i opisami kolumn
- Walidacja danych w kazdym wierszu z raportowaniem bledow
- Automatyczne tworzenie paczek (batch) dla wybranego miesiaca/roku
- **Wykrywanie duplikatow** - system zapobiega podwojnemu importowi tych samych faktur

### 2.3 Weryfikacja faktur przez klienta

- **Przejrzysty interfejs** - lista faktur z pelnym podgladem danych
- **Akceptacja / Odrzucenie** - jednym kliknieciem z opcjonalnym komentarzem
- **Weryfikacja zbiorcza** - akceptacja lub odrzucenie wielu faktur naraz
- **Przypisanie Miejsca Powstawania Kosztow (MPK)** - klient moze przypisac fakture do centrum kosztow
- **Automatyczna finalizacja** - po zweryfikowaniu wszystkich faktur system automatycznie generuje raporty

### 2.4 Centra kosztow (MPK)

- Wlaczanie/wylaczanie MPK per klient
- Do 10 centrow kosztow na klienta
- Raporty rozdzielone wg centrow kosztow
- Obowiazek lub opcja przypisania MPK przy weryfikacji

### 2.5 Raporty i eksporty

| Format | Zawartosc |
|--------|-----------|
| **PDF** | Raport zaakceptowanych faktur (A4 landscape, tabelaryczny) |
| **Excel (XLSX)** | Raport zaakceptowanych faktur (sformatowany, z podsumowaniem) |
| **JPK_FA(3) XML** | Plik JPK w formacie v3.0 zgodny ze schematem MF (dla faktur z KSeF) |
| **PDF odrzuconych** | Osobny raport odrzuconych faktur |
| **Excel odrzuconych** | Osobny arkusz odrzuconych faktur |

- Raporty generowane automatycznie po finalizacji paczki
- Raporty per centrum kosztow (jesli wlaczone MPK)
- Mozliwosc pobrania w dowolnym momencie z panelu

### 2.6 Integracja z KSeF (Krajowy System e-Faktur)

- **Pelna integracja z KSeF API v2** - srodowisko produkcyjne
- **Trzy metody uwierzytelniania:**
  - Token API
  - Certyfikat kwalifikowany (PFX/P12)
  - Certyfikat wydany przez KSeF
- **Pobieranie faktur** - bezposredni import faktur z KSeF do systemu
- **Zarzadzanie certyfikatami** - upload, walidacja, sprawdzanie waznosci
- **Diagnostyka polaczenia** - test konfiguracji KSeF z poziomu panelu
- **Per-klient konfiguracja** - kazdy klient moze miec wlasny token/certyfikat KSeF

### 2.7 Integracja z GUS (REGON API)

- **Wyszukiwanie firmy po NIP** - automatyczne pobieranie danych z rejestru GUS
- Pobierane dane: nazwa firmy, REGON, adres, wojewodztwo, kod pocztowy
- Automatyczne uzupelnianie formularza przy dodawaniu klienta
- Srodowisko testowe i produkcyjne

### 2.8 Powiadomienia e-mail

| Typ powiadomienia | Odbiorca | Kiedy |
|-------------------|----------|-------|
| **Nowe faktury** | Klient | Po zaimportowaniu nowej paczki faktur |
| **Przypomnienie o terminie** | Klient | N dni przed terminem weryfikacji (konfigurowalne) |
| **Raport finalizacji** | Biuro ksiegowe | Po zakonczeniu weryfikacji (z zalacznikami PDF/Excel/JPK) |
| **Reset hasla** | Uzytkownik | Po zadaniu resetu hasla |

- Wszystkie wiadomosci w jezyku polskim lub angielskim (wg preferencji uzytkownika)
- Konfiguracja SMTP z poziomu panelu administratora

### 2.9 Wyszukiwanie i filtrowanie faktur

- **Filtrowanie po statusie** - wyswietlanie faktur oczekujacych, zaakceptowanych lub odrzuconych
- **Wyszukiwanie tekstowe** - szukanie po numerze faktury, nazwie sprzedawcy lub NIP
- **Przycisk czyszczenia filtrow** - szybki powrot do pelnej listy
- Filtrowanie dostepne w panelach: administratora, biura ksiegowego i klienta

### 2.10 Paginacja

- **Paginacja list** - klientow, paczek faktur, dziennika audytu
- Konfigurowalna liczba rekordow na stronie (25/50)
- Inteligentna nawigacja stronami z elipsa (...)
- Zachowanie parametrow filtrowania przy nawigacji

### 2.11 Automatyzacja (CRON)

- **Auto-akceptacja po terminie** - faktury niezweryfikowane w terminie sa automatycznie akceptowane
- **Automatyczne powiadomienia** - wysylka przypomnien o zblizajacym sie terminie
- **Auto-import z KSeF** - cykliczne pobieranie faktur z KSeF (konfigurowalny dzien miesiaca)
- **Powiadomienia o wygasaniu certyfikatow KSeF** - email 30, 14 i 7 dni przed wygasnieciem
- **Automatyczne czyszczenie danych** - usuwanie starych plikow tymczasowych, wygaslych sesji, tokenow resetowania hasel, starych logow
- Wszystkie zadania uruchamiane codziennie o 8:00

### 2.12 Powiadomienia w aplikacji (in-app)

- **Ikona dzwonka w nawigacji** z licznikiem nieprzeczytanych
- Powiadomienia per uzytkownik (admin, biuro, klient)
- Typy: info, sukces, ostrzezenie, blad
- Oznaczanie jako przeczytane (pojedynczo lub wszystkie)
- Automatyczne czyszczenie starych powiadomien (>90 dni)

### 2.13 Webhooks

- **Integracja z systemami zewnetrznymi** przez HTTP webhooks
- Zdarzenia: batch.created, batch.finalized, invoice.verified, import.completed
- **Podpis HMAC-SHA256** na kazdym wywolaniu
- Konfiguracja per klient lub globalna
- Log dostarczenia z kodem HTTP i czasem odpowiedzi

### 2.14 Raporty zbiorcze

- Raport zbiorczy dla wielu klientow jednoczesnie
- Filtrowanie po zakresie dat
- Podsumowanie: laczna liczba faktur, statusy, wartosc brutto

### 2.15 Weryfikacja NIP w VIES (UE)

- **Integracja z systemem VIES** (VAT Information Exchange System)
- Weryfikacja aktywnosci numeru VAT w dowolnym kraju UE
- Odpytywanie SOAP API Komisji Europejskiej

### 2.16 Tryb ciemny (Dark Mode)

- **Przelacznik w nawigacji** - ikona ksiezyca
- Zapis preferencji w localStorage (bez reloadu)
- Pelne pokrycie: tabele, formularze, karty, wykresy, alerty, modalne

### 2.17 Responsywnosc mobilna / PWA

- **Web App Manifest** - mozliwosc dodania do ekranu glownego
- Meta tagi dla iOS/Android (standalone mode)
- Touch-friendly: minimalne rozmiary przyciskow (38px), pola formularzy (40px)
- Responsywny layout na tablet i telefon
- Horizontalne przewijanie tabel na malych ekranach
- Wsparcie dla safe area (notch devices)

### 2.18 Uwierzytelnianie dwuetapowe (2FA/TOTP)

- **TOTP RFC 6238** - standardowy protokol kompatybilny ze wszystkimi popularnymi aplikacjami
- Kompatybilnosc z: Google Authenticator, Microsoft Authenticator, Authy, FreeOTP, 1Password, Bitwarden
- **Kod QR** - skanowanie w aplikacji uwierzytelniajacej (generowany po stronie klienta - klucz nie opuszcza serwera)
- **Kody zapasowe** - 8 jednorazowych kodow zapasowych (haszowane SHA-256)
- **Wymuszenie 2FA** przez administratora (osobno dla adminow i zwyklych uzytkownikow)
- **Panel bezpieczenstwa** - kazdy uzytkownik moze samodzielnie wlaczyc/wylaczyc 2FA
- **Okno czasowe ±30s** - tolerancja na drobne roznice zegarow

---

## 3. BEZPIECZENSTWO

### 3.1 Ochrona danych

- **Szyfrowanie AES-256-GCM** - certyfikaty i tokeny KSeF szyfrowane w bazie danych
- **Szyfrowanie SSL/TLS** - cala komunikacja przez HTTPS
- **Wymuszenie HTTPS** - automatyczny redirect z HTTP na HTTPS
- **Dane przechowywane wylacznie w Polsce/UE**
- **Eksport danych klienta (RODO/GDPR)** - klient moze pobrac wszystkie swoje dane w formacie ZIP (JSON + CSV)
- **Blokada bezposredniego dostepu do plikow storage** - zabezpieczenie .htaccess

### 3.2 Uwierzytelnianie

- **Hasla hashowane bcrypt** (cost factor 12)
- **Minimalna dlugosc hasla: 12 znakow** + wielkie/male litery + cyfry + znaki specjalne
- **Wymuszenie zmiany hasla** - przy pierwszym logowaniu i co 90 dni
- **Ochrona przed brute-force** - blokada po 5 nieudanych probach na 15 minut
- **Ochrona sesji** - automatyczne wylogowanie po 30 min nieaktywnosci
- **Regeneracja sesji** - zapobieganie session fixation
- **Uwierzytelnianie dwuetapowe (2FA/TOTP)** - kompatybilne z Google Authenticator, Microsoft Authenticator, Authy, FreeOTP
  - Konfiguracja przez kod QR lub reczne wprowadzenie klucza
  - Jednorazowe kody zapasowe (recovery codes) z hashowaniem SHA-256
  - Administrator moze wymusic 2FA dla wszystkich uzytkownikow lub tylko adminow
  - Mozliwosc wlaczenia/wylaczenia 2FA przez kazdego uzytkownika w panelu bezpieczenstwa
  - Czysta implementacja PHP (RFC 6238) - brak zewnetrznych zaleznosci

### 3.3 Zabezpieczenia aplikacji

- Ochrona przed **SQL Injection** (prepared statements + quoting)
- Ochrona przed **XSS** (htmlspecialchars na wszystkich danych wyjsciowych)
- Ochrona przed **CSRF** (tokeny w formularzach)
- Ochrona przed **XXE** (wylaczone entity loader w XML)
- Ochrona przed **Clickjacking** (X-Frame-Options: SAMEORIGIN)
- Ochrona przed **MIME sniffing** (X-Content-Type-Options: nosniff)
- **Content Security Policy (CSP)** - ograniczenie zrodel skryptow i stylow
- **HSTS** - wymuszenie HTTPS na poziomie przegladarki
- Walidacja rozmiaru plikow (max 10MB import, max 5MB logo)
- Blokada uploadu plikow wykonywalnych
- **Strona 403** - dedykowana strona bledu braku dostepu
- **Wykrywanie duplikatow faktur** - zapobieganie podwojnemu importowi (po numerze, NIP sprzedawcy i kwocie brutto)
- **IP whitelist per klient** - ograniczenie dostepu do wybranych adresow IP z obsluga CIDR
- **Weryfikacja NIP w VIES** - sprawdzanie aktywnosci VAT w systemie UE

### 3.4 Audyt i monitorowanie

- **Pelny dziennik audytu** - kazda akcja w systemie jest logowana
- **Historia logowan** - udane i nieudane proby z adresem IP
- **Sledzenie sesji** - aktywne sesje uzytkownikow
- **Logi operacji KSeF** - pelna historia komunikacji z KSeF API

---

## 4. ADMINISTRACJA

### 4.1 Zarzadzanie klientami

- Dodawanie, edycja, dezaktywacja klientow
- Masowy import klientow z pliku CSV (z automatycznym generowaniem hasel)
- Przypisywanie klientow do biur ksiegowych
- Konfiguracja KSeF per klient
- Konfiguracja centrow kosztow per klient

### 4.2 Zarzadzanie biurami ksiegowymi

- Dodawanie, edycja biur
- Nadpisywanie globalnych ustawien per biuro:
  - Termin weryfikacji
  - Auto-akceptacja
  - Dni powiadomien

### 4.3 Branding / Personalizacja

- Wlasna nazwa systemu i opis
- Wlasne logo (PNG, JPG, WebP)
- Konfigurowalne kolory (podstawowy, dodatkowy, akcent)
- Edytowalny tekst polityki prywatnosci

### 4.4 Impersonacja

- Administrator moze zalogowac sie jako dowolny klient lub biuro
- Pelne logowanie akcji impersonacji w dzienniku audytu
- Mozliwosc powrotu do sesji admina jednym kliknieciem

---

## 5. WYMAGANIA TECHNICZNE

### 5.1 Serwer

- PHP 8.1+ z rozszerzeniami: PDO, MySQL, mbstring, GD, ZIP
- MySQL 5.7+ / MariaDB 10.3+
- Apache z mod_rewrite lub nginx
- SSL/TLS (Let's Encrypt lub komercyjny)
- SMTP do wysylki e-mail

### 5.2 Hosting

- Kompatybilny z Plesk (automatyczna konfiguracja)
- Automatyczny instalator (skrypt bash)
- Automatyczny updater (deploy.sh)
- Domena: portal.faktupilot.pl

### 5.3 Przegladarka klienta

- Chrome, Firefox, Edge, Safari (najnowsze wersje)
- Responsywny interfejs (desktop + tablet)

---

## 6. JEZYKI

- **Polski** - domyslny
- **Angielski** - pelne tlumaczenie interfejsu i e-maili
- Mozliwosc indywidualnego wyboru jezyka przez kazdego uzytkownika

---

## 7. KORZYSCI DLA KLIENTA KONCOWEGO

1. **Oszczednosc czasu** - weryfikacja faktur online zamiast papierowo
2. **Kontrola kosztow** - przypisywanie faktur do centrow kosztow (MPK)
3. **Pelna integracja z KSeF** - automatyczny import e-faktur
4. **Bezpieczenstwo** - szyfrowanie danych, RODO, audyt
5. **Automatyzacja** - powiadomienia, przypomnienia, auto-akceptacja
6. **Raporty** - PDF, Excel, JPK - gotowe do ksiegowania
7. **Dwujezycznosc** - interfejs w jezyku polskim i angielskim

## 8. KORZYSCI DLA BIURA KSIEGOWEGO

1. **Centralne zarzadzanie** - wszystcy klienci w jednym panelu
2. **Szybki import** - Excel, CSV, KSeF - wiele formatow
3. **Automatyczne raporty** - generowane po weryfikacji przez klienta
4. **Powiadomienia** - system sam przypomina klientom o terminie
5. **Kontrola** - pelna historia weryfikacji i komentarzy klientow
6. **JPK_FA(3)** - automatyczny eksport do formatu JPK
7. **Elastycznosc** - nadpisywanie ustawien globalnych per biuro

## 9. KORZYSCI DLA OPERATORA (SPRZEDAWCY)

1. **Model SaaS** - powtarzalny przychod miesieczny
2. **Wielotenancy** - wielu klientow i biur na jednej instancji
3. **Branding** - mozliwosc personalizacji pod wlasna marke
4. **Automatyzacja** - minimalna obsluga manualna
5. **Audyt i compliance** - pelna zgodnos z RODO i przepisami podatkowymi
6. **Skalowalnosc** - latwe dodawanie nowych klientow i biur

---

## 10. PROPOZYCJE DALSZEGO ROZWOJU

Ponizej lista propozycji funkcjonalnosci do wdrozenia w przyszlych wersjach:

| # | Propozycja | Priorytet | Status |
|---|-----------|-----------|--------|
| 1 | Eksport danych klienta RODO (ZIP) | Wysoki | Wdrozone |
| 2 | Dwuskładnikowe uwierzytelnianie (2FA/TOTP) | Wysoki | Wdrozone |
| 3 | Dashboard z wykresami/statystykami | Sredni | Wdrozone |
| 4 | Wyszukiwanie/filtrowanie faktur | Wysoki | Wdrozone |
| 5 | Paginacja list | Wysoki | Wdrozone |
| 6 | Powiadomienia w aplikacji (in-app) | Sredni | Wdrozone |
| 7 | API REST dla integracji zewnetrznych | Sredni | Do wdrozenia |
| 8 | Webhooks dla zdarzen systemowych | Niski | Wdrozone |
| 9 | Raporty zbiorcze (wiele paczek/klientow) | Sredni | Wdrozone |
| 10 | Automatyczna weryfikacja NIP w VIES (EU) | Niski | Wdrozone |
| 11 | IP whitelist per klient | Sredni | Wdrozone |
| 12 | Eksport dziennika audytu do CSV | Niski | Wdrozone |
| 13 | Strona 403 (brak dostepu) | Wysoki | Wdrozone |
| 14 | Czyszczenie starych danych w CRON | Wysoki | Wdrozone |
| 15 | Powiadomienie o wygasaniu certyfikatu KSeF | Wysoki | Wdrozone |
| 16 | Szablon importu Excel do pobrania | Wysoki | Wdrozone |
| 17 | Wykrywanie duplikatow faktur | Wysoki | Wdrozone |
| 18 | Tryb ciemny (dark mode) | Niski | Wdrozone |
| 19 | Responsywnosc mobilna / PWA | Sredni | Wdrozone |
| 20 | Integracja z systemami ERP (np. Comarch, Sage) | Sredni | Do wdrozenia |
