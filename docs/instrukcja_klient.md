# INSTRUKCJA OBSLUGI - PANEL KLIENTA

## BiLLU Financial Solutions - portal.billu.pl

---

## 1. PIERWSZE LOGOWANIE

### 1.1 Dane dostepu

Dane logowania (NIP i haslo tymczasowe) otrzymasz od swojego biura ksiegowego lub
administratora systemu.

### 1.2 Logowanie

1. Wejdz na strone: **https://portal.billu.pl**
2. Wybierz typ uzytkownika: **Klient**
3. Wpisz swoj **NIP** (10 cyfr, bez myslnikow)
4. Wpisz **haslo tymczasowe** otrzymane od biura
5. Kliknij **Zaloguj sie**

### 1.3 Zmiana hasla tymczasowego

Przy pierwszym logowaniu system wymusi zmiane hasla:

1. Wpisz **obecne haslo** (tymczasowe)
2. Wpisz **nowe haslo** - musi spelniac wymagania:
   - Minimum **12 znakow**
   - Co najmniej jedna **mala litera** (a-z)
   - Co najmniej jedna **wielka litera** (A-Z)
   - Co najmniej jedna **cyfra** (0-9)
   - Co najmniej jeden **znak specjalny** (!@#$% itp.)
3. **Powtorz nowe haslo**
4. Kliknij **Zmien haslo**

**Uwaga:** Haslo jest wazne przez 90 dni. Po tym czasie system ponownie wymusi zmiane.

### 1.4 Akceptacja polityki prywatnosci

Przy pierwszym logowaniu moze byc wymagana akceptacja polityki prywatnosci.
Przeczytaj jej tresc i kliknij **Akceptuje**.

---

## 2. PANEL GLOWNY (DASHBOARD)

Po zalogowaniu zobaczysz panel glowny z informacjami:

- **Aktywne paczki** - paczki faktur oczekujace na weryfikacje
- **Statystyki** - liczba faktur oczekujacych, zaakceptowanych i odrzuconych
- **Status KSeF** - informacja o konfiguracji integracji z KSeF

---

## 3. WERYFIKACJA FAKTUR

### 3.1 Przegladanie faktur

1. Kliknij na **aktywna paczke** w panelu glownym (lub przejdz do zakladki **Faktury**)
2. Zobaczysz liste faktur z danymi:
   - Numer faktury
   - Data wystawienia i data sprzedazy
   - Dane sprzedawcy (NIP, nazwa, adres)
   - Kwoty: netto, VAT, brutto
   - Waluta
   - Status weryfikacji

### 3.2 Akceptacja faktury

1. Przy wybranej fakturze kliknij **Akceptuj**
2. Opcjonalnie dodaj **komentarz** (np. "Faktura poprawna, dotyczy zamowienia #123")
3. Jesli wlaczone sa centra kosztow (MPK) - wybierz odpowiednie **MPK** z listy
4. Kliknij **Zapisz**

### 3.3 Odrzucenie faktury

1. Przy wybranej fakturze kliknij **Odrzuc**
2. Opcjonalnie dodaj **komentarz** wyjasniajacy powod odrzucenia
   (np. "Faktura nie dotyczy naszej firmy", "Bledna kwota")
3. Kliknij **Zapisz**

### 3.4 Weryfikacja zbiorcza

Jesli chcesz zaakceptowac lub odrzucic wiele faktur naraz:

1. W widoku listy faktur kliknij **Weryfikacja zbiorcza**
2. Zaznacz faktury, ktore chcesz zweryfikowac
3. Wybierz akcje: **Akceptuj zaznaczone** lub **Odrzuc zaznaczone**
4. Opcjonalnie wybierz **MPK** (zostanie przypisane do wszystkich zaznaczonych)
5. Kliknij **Wykonaj**

### 3.5 Centra kosztow (MPK)

Jesli Twoje biuro wlaczilo centra kosztow:

- Przy kazdej fakturze pojawi sie lista rozwijana z dostepnymi MPK
- Mozesz przypisac fakture do odpowiedniego centrum kosztow
- Raporty beda generowane oddzielnie dla kazdego MPK
- Jesli MPK jest wymagane - musisz je wybrac, aby zaakceptowac fakture

### 3.6 Automatyczna finalizacja

- Gdy zweryfikujesz **wszystkie faktury** w paczce, system automatycznie:
  1. Generuje raporty (PDF + Excel + opcjonalnie JPK)
  2. Wysyla raporty e-mailem do biura ksiegowego
  3. Oznacza paczke jako zfinalizowana

- Jesli **nie zweryfikujesz faktur w terminie**, system moze automatycznie zaakceptowac
  pozostale faktury (zalezy od ustawien biura).

---

## 4. RAPORTY

### 4.1 Przegladanie raportow

1. Przejdz do zakladki **Raporty**
2. Zobaczysz liste wygenerowanych raportow z informacjami:
   - Okres (miesiac/rok)
   - Data wygenerowania
   - Typ raportu

### 4.2 Pobieranie raportow

Kliknij na wybrany raport i wybierz format:

| Format | Opis | Kiedy uzywac |
|--------|------|-------------|
| **PDF** | Raport w formacie PDF (A4, tabelaryczny) | Do przegladania i druku |
| **Excel** | Arkusz XLSX z formatowaniem | Do dalszej obrobki w Excelu |
| **JPK XML** | Plik JPK_FA(3) | Do importu do systemu ksiegowego (tylko faktury z KSeF) |

---

## 5. IMPORT FAKTUR Z KSEF

### 5.1 Konfiguracja KSeF (jednorazowo)

Aby korzystac z importu z KSeF, musisz najpierw skonfigurowac uwierzytelnianie:

#### Opcja A: Token API

1. Zaloguj sie do portalu KSeF: https://ksef.mf.gov.pl
2. Wygeneruj token API w panelu KSeF
3. W BiLLU przejdz do **KSeF > Konfiguracja**
4. Wklej token w pole **Token API**
5. Kliknij **Zapisz**

#### Opcja B: Certyfikat kwalifikowany

1. Przygotuj certyfikat kwalifikowany w formacie PFX lub P12
2. W BiLLU przejdz do **KSeF > Konfiguracja**
3. Kliknij **Wgraj certyfikat**
4. Wybierz plik certyfikatu i podaj haslo
5. System zweryfikuje certyfikat i wyswietli jego dane (waznos, odcisk palca)

**Uwaga:** BiLLU obsluguje wylacznie srodowisko **produkcyjne** KSeF.
Certyfikaty i tokeny musisz wygenerowac samodzielnie w systemie KSeF.

### 5.2 Test polaczenia

Po skonfigurowaniu KSeF mozesz przetestowac polaczenie:

1. Przejdz do **KSeF > Konfiguracja**
2. Kliknij **Testuj polaczenie**
3. System sprawdzi:
   - Polaczenie z serwerem KSeF
   - Poprawnosc tokenu/certyfikatu
   - Uprawnienia do pobierania faktur

### 5.3 Import faktur

1. Przejdz do **KSeF > Import**
2. Wybierz **miesiac** i **rok**
3. Kliknij **Importuj z KSeF**
4. System automatycznie:
   - Pobierze faktury z KSeF za wybrany okres
   - Utworzy nowa paczke (lub doda do istniejacej)
   - Wyswietli podsumowanie importu

### 5.4 Zarzadzanie certyfikatami

W zakladce **KSeF > Konfiguracja** mozesz:

- Przegladac wgrane certyfikaty (nazwa, typ, waznosc)
- Usuwac nieuzywane certyfikaty
- Sprawdzac status certyfikatow w systemie KSeF
- Przelaczac miedzy metoda tokenowa a certyfikatowa

---

## 6. ZMIANA JEZYKA

1. Kliknij **Zmien jezyk** w gornym menu
2. Wybierz: **Polski** lub **English**
3. Jezyk zostanie zapamietany dla Twojego konta

---

## 7. ZMIANA HASLA

1. Przejdz do **Zmien haslo** w gornym menu
2. Wpisz **obecne haslo**
3. Wpisz **nowe haslo** (spelniane wymagania jak przy pierwszym logowaniu)
4. Powtorz nowe haslo
5. Kliknij **Zmien haslo**

**Uwaga:** Haslo jest wazne przez 90 dni.

---

## 8. RESETOWANIE HASLA (ZAPOMNIANE HASLO)

Jesli zapomniales hasla:

1. Na stronie logowania kliknij **Zapomnialem hasla**
2. Wpisz swoj **NIP**
3. Kliknij **Wyslij link resetujacy**
4. Sprawdz skrzynke e-mail (rowniez folder SPAM)
5. Kliknij link w wiadomosci (wazny 1 godzine)
6. Ustaw nowe haslo

---

## 9. POWIADOMIENIA E-MAIL

System wysyla automatyczne powiadomienia:

| Powiadomienie | Kiedy | Tresc |
|---------------|-------|-------|
| **Nowe faktury** | Po imporcie nowej paczki | Informacja o liczbie faktur i terminie weryfikacji |
| **Przypomnienie** | N dni przed terminem | Przypomnienie o niezweryfikowanych fakturach |
| **Reset hasla** | Po zadaniu resetu | Link do zmiany hasla (wazny 1h) |

---

## 10. BEZPIECZENSTWO - DOBRE PRAKTYKI

1. **Nie udostepniaj** swojego loginu i hasla innym osobom
2. **Nie zapisuj** hasla w przegladarce na wspoldzielonych komputerach
3. **Wyloguj sie** po zakonczeniu pracy (gorne menu > Wyloguj)
4. **Zmien haslo** natychmiast, jesli podejrzewasz, ze ktos mogl je poznac
5. System automatycznie wyloguje Cie po **30 minutach** nieaktywnosci
6. Po **5 nieudanych probach** logowania konto zostanie zablokowane na 15 minut

---

## 11. NAJCZESCIEJ ZADAWANE PYTANIA (FAQ)

**P: Nie moge sie zalogowac - co robic?**
O: Sprawdz, czy wpisujesz poprawny NIP (10 cyfr, bez myslnikow). Jesli zapomniales hasla,
uzyj opcji "Zapomnialem hasla". Po 5 blednych probach konto blokuje sie na 15 minut.

**P: Nie widze zadnych faktur do weryfikacji.**
O: Faktury musza byc najpierw zaimportowane przez biuro ksiegowe. Skontaktuj sie z biurem.

**P: Chce zmienic decyzje (akceptacja/odrzucenie) - czy to mozliwe?**
O: Po zatwierdzeniu decyzji mozesz ja zmienic, dopoki paczka nie zostala zfinalizowana.

**P: Co oznacza "Auto-akceptacja"?**
O: Jesli nie zweryfikujesz faktur w wyznaczonym terminie, system automatycznie je zaakceptuje.

**P: Gdzie znajde raporty JPK?**
O: Raporty JPK sa generowane automatycznie tylko dla faktur zaimportowanych z KSeF.
Znajdziesz je w zakladce Raporty.

**P: Jak skonfigurowac KSeF?**
O: Przejdz do KSeF > Konfiguracja. Potrzebujesz tokenu API lub certyfikatu kwalifikowanego
wygenerowanego w systemie KSeF (https://ksef.mf.gov.pl).

---

## 12. KONTAKT I WSPARCIE

W razie problemow skontaktuj sie z:

- **Biuro ksiegowe** - w sprawach dotyczacych faktur i terminow
- **Wsparcie techniczne BiLLU** - w sprawach technicznych:
  - E-mail: [EMAIL WSPARCIA]
  - Telefon: [TELEFON]
