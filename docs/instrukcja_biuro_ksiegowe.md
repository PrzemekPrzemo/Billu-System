# INSTRUKCJA OBSLUGI - PANEL BIURA KSIEGOWEGO

## BiLLU Financial Solutions - portal.faktupilot.pl

---

## 1. PIERWSZE LOGOWANIE

### 1.1 Dane dostepu

Dane logowania (NIP lub e-mail oraz haslo) otrzymasz od administratora systemu BiLLU.

### 1.2 Logowanie

1. Wejdz na strone: **https://portal.faktupilot.pl**
2. Wybierz typ uzytkownika: **Biuro ksiegowe**
3. Wpisz swoj **NIP** lub **adres e-mail**
4. Wpisz **haslo**
5. Kliknij **Zaloguj sie**

### 1.3 Zmiana hasla

Jesli system wymusi zmiane hasla (wygasniecie po 90 dniach):

1. Wpisz **obecne haslo**
2. Wpisz **nowe haslo** spelniajace wymagania:
   - Minimum **12 znakow**
   - Male i wielkie litery, cyfry, znaki specjalne
3. Powtorz nowe haslo
4. Kliknij **Zmien haslo**

---

## 2. PANEL GLOWNY (DASHBOARD)

Po zalogowaniu zobaczysz:

- **Lista klientow** - Twoi przypisani klienci z podsumowaniem statusu
- **Aktywne paczki** - paczki faktur wymagajace uwagi
- **Liczba oczekujacych faktur** - laczna liczba niezweryfikowanych faktur

---

## 3. ZARZADZANIE KLIENTAMI

### 3.1 Lista klientow

1. Przejdz do zakladki **Klienci**
2. Zobaczysz liste przypisanych klientow z informacjami:
   - Nazwa firmy, NIP
   - Liczba przetworzonych faktur
   - Status (aktywny/nieaktywny)

### 3.2 Edycja ustawien klienta

1. Kliknij **Edytuj** przy wybranym kliencie
2. Mozesz zarzadzac:

#### Centra kosztow (MPK)

- Wlacz/wylacz **centra kosztow** dla klienta
- Dodaj nazwy centrow kosztow (np. "Dzial IT", "Dzial Handlowy", "Administracja")
- Maksymalnie **10 centrow kosztow** na klienta
- Ustaw kolejnosc wyswietlania
- Aktywuj/dezaktywuj poszczegolne centra

#### Konfiguracja KSeF

- Wpisz **token KSeF** klienta (nadpisuje globalny token)
- Token jest szyfrowany i bezpiecznie przechowywany
- Klient moze tez samodzielnie skonfigurowac KSeF ze swojego panelu

---

## 4. IMPORT FAKTUR

### 4.1 Import z pliku Excel (XLSX/XLS)

#### Przygotowanie pliku

Plik Excel musi zawierac nastepujace kolumny (w wierszu 1 - naglowki):

| Kolumna | Nazwa | Opis | Przyklad |
|---------|-------|------|---------|
| A | NIP Sprzedawcy | 10 cyfr | 1234567890 |
| B | Nazwa Sprzedawcy | Pelna nazwa | ABC Sp. z o.o. |
| C | Adres Sprzedawcy | Pelny adres | ul. Kwiatowa 5, 00-001 Warszawa |
| D | Kontakt Sprzedawcy | Telefon/e-mail | info@abc.pl |
| E | NIP Nabywcy | 10 cyfr | 9876543210 |
| F | Nazwa Nabywcy | Pelna nazwa | XYZ S.A. |
| G | Adres Nabywcy | Pelny adres | ul. Lesna 10, 30-001 Krakow |
| H | Numer Faktury | Numer dokumentu | FV/2025/001 |
| I | Data Wystawienia | Format: RRRR-MM-DD | 2025-01-15 |
| J | Data Sprzedazy | Format: RRRR-MM-DD | 2025-01-14 |
| K | Waluta | Kod waluty | PLN |
| L | Kwota Netto | Liczba (2 miejsca) | 1000.00 |
| M | Kwota VAT | Liczba (2 miejsca) | 230.00 |
| N | Kwota Brutto | Liczba (2 miejsca) | 1230.00 |
| O | Pozycje (opcjonalnie) | JSON z pozycjami | [{"nazwa":"Usluga","ilosc":1}] |

#### Proces importu

1. Przejdz do **Import > Import z pliku**
2. Wybierz **klienta** z listy rozwijanej
3. Wybierz **miesiac** i **rok** (okres rozliczeniowy)
4. Kliknij **Wybierz plik** i wskaż plik Excel
5. Kliknij **Importuj**
6. System wyswietli podsumowanie:
   - Liczba zaimportowanych faktur
   - Lista bledow (jesli wystapily) z numerami wierszy

**Limity:** Maksymalny rozmiar pliku: **10 MB**

### 4.2 Import z pliku CSV/TXT

#### Format pliku

- Separator: **tabulator** lub **srednik** (wykrywany automatycznie)
- Kodowanie: **UTF-8** (z lub bez BOM)
- Kolumny w tej samej kolejnosci co w Excelu (patrz tabela powyzej)

#### Proces importu

Identyczny jak dla Excela (patrz punkt 4.1).

### 4.3 Import z KSeF

1. Przejdz do **Import > Import z KSeF**
2. Wybierz **klienta** (musi miec skonfigurowany token lub certyfikat KSeF)
3. Wybierz **miesiac** i **rok**
4. Kliknij **Importuj z KSeF**
5. System automatycznie:
   - Polaczy sie z KSeF
   - Pobierze faktury za wybrany okres
   - Utworzy paczke lub doda do istniejacej
   - Wyswietli podsumowanie

**Uwaga:** Klient musi wczesniej skonfigurowac dostep do KSeF (token lub certyfikat)
w swoim panelu lub poprzez edycje ustawien klienta w Twoim panelu.

---

## 5. ZARZADZANIE PACZKAMI (BATCH)

### 5.1 Lista paczek

1. Przejdz do zakladki **Paczki**
2. Zobaczysz liste paczek z informacjami:
   - Klient
   - Okres (miesiac/rok)
   - Status: **oczekuje** / **w trakcie** / **zfinalizowana**
   - Termin weryfikacji
   - Liczba faktur (oczekujace / zaakceptowane / odrzucone)

### 5.2 Szczegoly paczki

Kliknij na paczke, aby zobaczyc:
- Pelna liste faktur z danymi
- Status weryfikacji kazdej faktury
- Komentarze klientow
- Przypisane centra kosztow (MPK)
- Statystyki (ile zaakceptowanych, odrzuconych, oczekujacych)

### 5.3 Cykl zycia paczki

```
Import faktur
     |
     v
[Paczka utworzona] --> Powiadomienie e-mail do klienta
     |
     v
[Klient weryfikuje] --> Przypomnienie N dni przed terminem
     |
     v
[Wszystkie zweryfikowane] --> Automatyczna finalizacja
     |                        |
     v                        v
[Raporty wygenerowane] --> E-mail z raportami do biura
```

Jezeli klient nie zweryfikuje faktur w terminie i wlaczona jest auto-akceptacja:
- System automatycznie zaakceptuje niezweryfikowane faktury
- Wygeneruje raporty
- Wysle je e-mailem

---

## 6. RAPORTY

### 6.1 Dostep do raportow

1. Przejdz do zakladki **Raporty**
2. Filtruj po kliencie lub okresie
3. Kliknij na raport, aby pobrac

### 6.2 Formaty raportow

| Format | Zawartosc | Zastosowanie |
|--------|-----------|-------------|
| **PDF zaakceptowanych** | Tabela zaakceptowanych faktur z kwotami i komentarzami | Druk, archiwum |
| **Excel zaakceptowanych** | Arkusz XLSX, sformatowany, z sumami | Import do systemu FK |
| **JPK_FA(3) XML** | Plik JPK w formacie v3.0 (schemat MF 2022-02-17) | Import do systemow podatkowych |
| **PDF odrzuconych** | Tabela odrzuconych faktur z komentarzami | Dokumentacja |
| **Excel odrzuconych** | Arkusz XLSX odrzuconych faktur | Analiza |

### 6.3 Raporty per MPK

Jesli klient ma wlaczone centra kosztow, raporty sa generowane oddzielnie
dla kazdego centrum kosztow - mozesz pobrac raport dla konkretnego MPK.

---

## 7. IMPORT MASOWY KLIENTOW

Jesli musisz dodac wielu klientow jednoczesnie, skontaktuj sie z administratorem
systemu - posiada on funkcje masowego importu z pliku CSV.

Format pliku do importu masowego (przygotuj dla administratora):

```
NIP;Nazwa firmy;Przedstawiciel;Email;Email raportowy
1234567890;ABC Sp. z o.o.;Jan Kowalski;jan@abc.pl;raporty@abc.pl
9876543210;XYZ S.A.;Anna Nowak;anna@xyz.pl;ksiegowosc@xyz.pl
```

Po imporcie administrator przekaze Ci liste wygenerowanych hasel tymczasowych
do przekazania klientom.

---

## 8. ZMIANA JEZYKA

1. Kliknij **Zmien jezyk** w gornym menu
2. Wybierz: **Polski** lub **English**
3. Jezyk zostanie zapamietany

---

## 9. SCHEMAT PRACY - KROK PO KROKU

### Miesięczny cykl pracy:

#### 1. Poczatek miesiaca - Import faktur
- Zbierz faktury zakupowe klientow
- Przygotuj plik Excel/CSV lub uzyj importu z KSeF
- Zaimportuj faktury dla kazdego klienta, wybierajac odpowiedni okres

#### 2. Po imporcie - Powiadomienie klienta
- System automatycznie wysle e-mail do klienta z informacja o nowych fakturach
- Klient loguje sie i weryfikuje faktury

#### 3. Monitorowanie postepow
- Sprawdzaj regularnie status paczek w zakladce **Paczki**
- System wysle automatyczne przypomnienie N dni przed terminem

#### 4. Po weryfikacji - Raporty
- Po zweryfikowaniu wszystkich faktur przez klienta system automatycznie
  wygeneruje raporty i wysle je na Twoj e-mail
- Pobierz raporty z zakladki **Raporty** (PDF, Excel, JPK)

#### 5. Ksiegowanie
- Uzyj wygenerowanych raportow (Excel / JPK) do zaksiegowania faktur
  w systemie finansowo-ksiegowym

---

## 10. NAJCZESCIEJ ZADAWANE PYTANIA (FAQ)

**P: Jak dodac nowego klienta?**
O: Skontaktuj sie z administratorem systemu. Administrator moze dodac klienta
pojedynczo lub masowo z pliku CSV.

**P: Klient nie moze sie zalogowac.**
O: Sprawdz, czy klient uzywa poprawnego NIP. Jesli zapomnial hasla, niech uzyje
opcji "Zapomnialem hasla" na stronie logowania. Po 5 blednych probach konto
blokuje sie na 15 minut.

**P: Import z pliku zwraca bledy.**
O: Sprawdz format pliku - kolumny musza byc w prawidlowej kolejnosci.
Bledy sa raportowane z numerami wierszy - sprawdz wskazane wiersze.
Upewnij sie, ze NIP-y maja 10 cyfr, daty sa w formacie RRRR-MM-DD,
a kwoty uzywajakropki jako separatora dziesietnego.

**P: Klient nie weryfikuje faktur w terminie.**
O: System wysyla automatyczne przypomnienia. Jesli wlaczona jest auto-akceptacja,
po terminie niezweryfikowane faktury zostana automatycznie zaakceptowane.

**P: Jak zmienic termin weryfikacji?**
O: Skontaktuj sie z administratorem systemu. Administrator moze ustawic indywidualny
termin dla Twojego biura, nadpisujac ustawienie globalne.

**P: Jak wlaczyc centra kosztow (MPK) dla klienta?**
O: Przejdz do Klienci > Edytuj (wybrany klient) > Centra kosztow.
Wlacz opcje i dodaj nazwy centrow kosztow.

**P: Czy moge importowac faktury z KSeF dla klienta?**
O: Tak, ale klient musi najpierw skonfigurowac token lub certyfikat KSeF.
Mozesz tez wpisac token klienta w jego ustawieniach.

---

## 11. BEZPIECZENSTWO - DOBRE PRAKTYKI

1. **Nie udostepniaj** swoich danych logowania
2. **Wyloguj sie** po zakonczeniu pracy
3. **Zmieniaj haslo** regularnie (system wymusi zmiane co 90 dni)
4. **Przekazuj hasla** klientom bezpiecznym kanalem (nie przez nieszyfrowany e-mail)
5. **Upewnij sie**, ze masz aktualne umowy powierzenia danych z klientami
6. System automatycznie wyloguje Cie po **30 minutach** nieaktywnosci

---

## 12. KONTAKT I WSPARCIE

W razie problemow technicznych:

- **E-mail:** [EMAIL WSPARCIA]
- **Telefon:** [TELEFON]
- **Godziny wsparcia:** [GODZINY]
