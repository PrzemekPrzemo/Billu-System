# BiLLU — Mapa integracji zewnętrznych (roadmap)

Dokument referencyjny. Inwentaryzacja istniejących połączeń + propozycje
nowych, pogrupowane wg priorytetu biznesowego dla biura księgowego.
Każda pozycja: **co robi**, **dlaczego BiLLU**, **koszt API**, **trudność**,
**ryzyka**.

## Spis treści

1. [Co już jest](#1-co-juz-jest)
2. [Wysoki priorytet (must-have)](#2-wysoki-priorytet)
3. [Średni priorytet (powinno-byc)](#3-sredni-priorytet)
4. [Niski priorytet (nice-to-have)](#4-niski-priorytet)
5. [Wzorzec architektoniczny dla każdej nowej integracji](#5-architektura)
6. [Kolejność wdrożeń](#6-kolejnosc)

## 1. Co już jest

Inwentaryzacja stanu na branchu `claude/analyze-billing-system-XRqfz`.
Każda kolumna „Service" wskazuje konkretną klasę w `src/Services/`.

| Obszar                | Service                              | Wykorzystanie                                  |
|-----------------------|--------------------------------------|-----------------------------------------------|
| KSeF (e-faktury)      | `KsefApiService`, `KsefCertificateService`, `KsefInvoiceSendService` | Wystawianie + wysyłka faktur do MF |
| GUS (REGON)           | `GusApiService`                      | Lookup firmy po NIP-ie                        |
| CEIDG                 | `CeidgApiService`                    | Lookup JDG / mikroprzedsiębiorca              |
| VIES (UE VAT)         | `ViesService`                        | Walidacja NIP UE przy fakturze WDT/WNT        |
| Biała lista VAT       | `WhiteListService`                   | Weryfikacja konta sprzedawcy z białą listą MF |
| NBP                   | `NbpExchangeRateService`             | Kursy walut do faktur walutowych              |
| JPK                   | `JpkV3Service`, `JpkVat7Service`, `JpkFaService` | Generacja JPK_V7M, JPK_FA, JPK_VAT |
| Bank ident.           | `BankIdentService`                   | Wyciągnięcie kodu banku z IBAN                |
| Elixir export         | `ElixirExportService`                | Polski format paczek przelewów                |
| ERP export            | `ErpExportService`                   | XML/CSV do Symfonia / Comarch / Subiekt       |
| SMTP / mail           | `MailService`, `MailQueueService`    | Async kolejka maili                           |
| **SIGNIUS**           | `SigniusApiService` *(nowy, PR #4)*  | E-podpisy umów                                |
| **SFTP biura**        | `SftpUploadService` *(nowy, PR #4)*  | Push plików klienta na SFTP biura             |
| Cache                 | `Cache` (Predis)                     | Redis, fallback no-op                         |
| pdftk                 | `ContractPdfService` *(nowy)*        | Wypełnianie aktywnych PDF                     |
| Geo IP                | `IpGeoService` *(nowy)*              | Lokalizacja zaufanych urządzeń (ip-api.com)   |

Architektonicznie wszystko jednolite: każda integracja ma jeden Service,
secrets w `.env` (lub w DB szyfrowane przez `Crypto`), retry/throttling
przez Cache albo dedykowaną kolejkę (jak `mail_queue`, `sftp_queue`).

---

## TL;DR (rekomendacje)

Następne **6 miesięcy** — w tej kolejności:

| # | Integracja                       | Wartość biznesowa                              | Trudność |
|---|----------------------------------|-----------------------------------------------|----------|
| 1 | **PSD2 / Open Banking**          | Auto-import wyciągów = koniec ręcznego CSV    | Wysoka   |
| 2 | **ZUS PUE / e-Płatnik**          | Wysyłka DRA/RCA bez aplikacji Płatnik         | Wysoka   |
| 3 | **OCR faktur kosztowych**        | Klient fota → faktura w systemie              | Średnia  |
| 4 | **e-Urząd Skarbowy (eUS)**       | JPK_V7M, statusy zwrotów, korespondencja KAS  | Wysoka   |
| 5 | **KRS API + CRBR**               | Pełne dane spółek + beneficjenci rzeczywiści  | Niska    |

Następne **6–18 miesięcy**: płatności linkiem, AI klasyfikacja faktur,
e-commerce (BaseLinker/Allegro), SMS/WhatsApp, Sentry/APM.

---

## 2. Wysoki priorytet

### 2.1. PSD2 / Open Banking — auto-import wyciągów bankowych

**Co robi:** Klient uwierzytelnia się raz w swoim banku (OAuth2), BiLLU
codziennie pobiera nowe transakcje + saldo. Można dopasowywać transakcje
do faktur (paid/unpaid), generować raport cash-flow.

**Dlaczego BiLLU:** dziś klient ręcznie wgrywa MT940 albo Elixir, biuro
sprawdza czy wpłaty matchują się z fakturami. PSD2 automatyzuje to całkowicie.

**Standard:** **Polish API** (PolishAPI ASPSP) — wszystkie polskie banki
oferują ten sam interfejs (`accounts`, `transactions`, `consents`).
Wymagana **licencja PSD2 (TPP/AISP)** od KNF — albo użyć pośrednika typu
**Kontomatik / Salt Edge / Tink / TrueLayer**.

**Trudność:** ⭐⭐⭐⭐ — najtrudniejsza z całej listy:
- regulacja KNF (licencja TPP albo umowa z licencjonowanym pośrednikiem)
- certyfikat eIDAS QWAC (qualified web auth) per środowisko (~3000 zł/rok)
- consent management — klient odnawia zgodę co 90 dni (SCA)
- per-bank quirki (każdy ma swoje endpointy mimo standardu)

**Koszt API:** Salt Edge ~0,15 €/account/miesiąc, Kontomatik ~50 zł/konto/m-c.
Przy 100 klientach biura ≈ 5 000 zł/m-c — biznesowo OK bo zastępuje to
~30 godzin pracy księgowej.

**Plan techniczny w BiLLU:**
- nowa migracja: `bank_connections` (client_id, provider, consent_token_enc, refresh_token_enc, last_sync_at, status)
- service `OpenBankingService` z adapterami `KontomatikAdapter`, `SaltEdgeAdapter`
- cron: codzienny `bank-sync.php` po analogii do `mail-worker.php`
- UI: w `/client/bank-accounts` przycisk „Połącz z bankiem przez PSD2"
- matching: kojarzenie `transactions.title + amount + sender_nip` z `invoices` → status `is_paid=1`

---

### 2.2. ZUS PUE — wysyłka deklaracji ZUS DRA/RCA bez aplikacji Płatnik

**Co robi:** ZUS oferuje API do wysyłki deklaracji elektronicznych —
zastępuje desktopową aplikację Płatnik dla biur obsługujących klientów
z pracownikami.

**Dlaczego BiLLU:** moduł HR (`payroll-zus`) już dziś generuje XML DRA/RCA
(`PayrollDeclarationService`). Brakuje **wysyłki**. Dziś biuro pobiera XML,
wgrywa ręcznie do Płatnika, podpisuje, wysyła. Z API: jeden klik.

**API:** ZUS PUE → moduł **Webservices** (interface ePłatnik). Wymaga:
- konto firmowe na ZUS PUE
- certyfikat kwalifikowany do podpisu (już może mieć przez SIGNIUS)
- profil zaufany / podpis

**Trudność:** ⭐⭐⭐⭐ — wysoka, bo:
- dokumentacja ZUS (`bip.zus.pl`) — XML schemy, SOAP envelope
- podpis XAdES-BES zgodny z ZUS (niestandardowy format)
- testy w środowisku TEST PUE wymagają osobnej rejestracji

**Koszt API:** **darmowe**. Koszt to czas dev (~2 tygodnie senior PHP +
prawnik znający tematykę ZUS).

**Plan techniczny w BiLLU:**
- nowy service `ZusPueService` (signEnvelope, sendDocument, getStatus)
- migracja `payroll_zus_submissions` (declaration_id, pue_id, status, response_xml, sent_at)
- worker: `zus-worker.php` co minutę (długie kolejki gdy ZUS ma awarie)
- UI: w `/office/hr/{clientId}/declarations` przycisk „Wyślij do ZUS"

---

### 2.3. OCR faktur kosztowych

**Co robi:** Klient fota fakturę z telefonu albo wgrywa skan/PDF — AI
wyciąga sprzedawcę, NIP, numer, kwoty, daty. BiLLU tworzy fakturę
kosztową bez ręcznego przepisywania.

**Dlaczego BiLLU:** to **największy time-saver** dla księgowej.
Klient mały (< 50 faktur kosztowych/m-c) → księgowa traci 4-6 godzin
na ręczne wpisywanie. OCR to robi w 3 sekundy.

**Dostawcy (od najlepszego do polskich faktur):**
- **Mindee** (FR, ma model „Polish Invoice") — 0,10 €/page, najprostsza integracja, REST + SDK PHP
- **AWS Textract** + custom training na polskich fakturach — 0,015 $/page raw, ale wymaga ML setup
- **Google Cloud Document AI** — 0,065 $/page (Invoice processor), bez polskiego specjalizowanego modelu
- **Azure Form Recognizer** (Document Intelligence) — 0,01 $/page Layout API, 1,50 $/1000 Invoice prebuilt
- **Polski rynek**: **DocFlowAI**, **Saldeo**, **Comarch IBARD** — droższe ale lokalne wsparcie

**Rekomendacja:** zacząć od **Mindee** (1-dniowa integracja, ma natywny
polski parser). Później migrować jak będzie potrzeba.

**Trudność:** ⭐⭐ — niska po API; wyzwanie jest w UI confidence-review
(księgowa musi widzieć "AI wpisał A=42, czy zatwierdzasz?").

**Koszt:** Mindee ~50 zł / 500 faktur. Dla biura z 30 klientami ~150 zł/m-c.

**Plan techniczny:**
- nowy service `InvoiceOcrService` (parsePdfOrImage)
- nowa kolumna `invoices.ocr_confidence_json` z polami AI + confidence per pole
- UI: w batch_detail → faktura z "AI" badge → modal review przed accept
- async — upload trafia do `ocr_queue`, worker odpala request do API

---

### 2.4. e-Urząd Skarbowy (eUS) — JPK_V7, korespondencja KAS

**Co robi:** Centralny portal MF do podatników. Wymiana JPK + odbiór
postanowień / wezwań / decyzji od urzędu skarbowego klienta.

**Dlaczego BiLLU:** dziś biuro klika ręcznie w eUS żeby wysłać JPK_V7M
albo sprawdzić status zwrotu VAT. API eliminuje przełączanie między
systemami.

**API:** **e-Urząd Skarbowy** ma **REST/SOAP** dla pełnomocników
podatkowych (UPL-1). Wymaga:
- pełnomocnictwa elektronicznego klienta dla biura (UPL-1 / UPL-1P)
- certyfikatu kwalifikowanego biura

**Trudność:** ⭐⭐⭐⭐ — porównywalna z KSeF (już macie), ale dokumentacja
mniej dojrzała.

**Koszt:** darmowe (państwowe). Koszt dev ~2-3 tygodnie.

**Plan techniczny:**
- service `EusApiService`, podobny pattern jak `KsefApiService`
- migracja `eus_submissions`, `eus_messages` (incoming KAS correspondence)
- UI: nowa zakładka **„Korespondencja KAS"** w `/office/clients/{id}`
- notyfikacje: nowa wiadomość od KAS → email do biura + wpis do `notifications`

---

### 2.5. KRS API + CRBR

**Co robi:** **KRS** = pełna baza spółek prawa handlowego (sp. z o.o., S.A.,
spółki komandytowe). Dane zarządu, wspólników, kapitał, status.
**CRBR** = Centralny Rejestr Beneficjentów Rzeczywistych — kto faktycznie
kontroluje spółkę (anti-money-laundering).

**Dlaczego BiLLU:** dla klientów-spółek `GusApiService` daje tylko nazwę
i adres. KRS dodaje członków zarządu (kogo wpisywać do faktur jako
osoby reprezentujące), wspólników (znaczenie podatkowe), CRBR
(obowiązkowy AML check przed pierwszą fakturą).

**API:** 
- **KRS REST API** (https://api-krs.ms.gov.pl) — darmowe, bez auth, JSON
- **CRBR API** — darmowe, ograniczone do 1000 zapytań/dzień bez umowy

**Trudność:** ⭐ — najprostsza z listy. JSON, brak auth, prosta struktura.

**Koszt:** darmowe.

**Plan techniczny:**
- service `KrsApiService::lookupByKrs($krsNumber)`, `lookupByNip($nip)`
- service `CrbrApiService::getBeneficiaries($nipOrKrs)`
- cache w Redis 30 dni (dane KRS zmieniają się rzadko)
- UI: w `/office/clients/{id}` panel „Dane z KRS" (pokazany jeśli klient
  ma `legal_form` = sp. z o.o. / S.A. / komandytowa)
- audit trail: AML check przy `Client::create` z office strony

---

## 3. Średni priorytet

### 3.1. Płatności linkiem (Przelewy24 / PayU / Stripe / Tpay)

**Co robi:** faktura sprzedaży klienta dostaje przycisk **„Zapłać online"**
— odbiorca klika, płaci kartą/BLIKiem/przelewem. Webhook wraca do BiLLU
i automatycznie oznacza fakturę jako opłaconą.

**Dlaczego BiLLU:** zwiększa ściągalność należności klientów biura
(badania BIK: faktura z linkiem płatniczym jest płacona średnio o
12 dni szybciej). Biuro może ten feature sprzedawać klientowi jako
upsell.

**Rekomendacja:** **Przelewy24** dla rynku polskiego (najwięcej metod
PL: BLIK, przelewy bankowe ze wszystkich banków, karty), prowizja
~1,3% + 0,30 zł.

**Trudność:** ⭐⭐ — średnio-niska. Sandbox + webhook signature są
dobrze udokumentowane.

**Plan techniczny:**
- service `Przelewy24Service` (createTransaction, verifyWebhook)
- migracja `payment_links` (invoice_id, provider, transaction_id, status, amount, paid_at)
- nowy endpoint `POST /webhooks/przelewy24` z weryfikacją CRC
- UI: w `templates/client/sales_view.php` button „Wygeneruj link płatniczy"

---

### 3.2. AI klasyfikacja faktur + asystent (OpenAI / Anthropic Claude)

**Co robi:** LLM analizuje opis pozycji faktury i automatycznie przypisuje
**KGZ/MPZP** (kategorię kosztu wg KSH), proponuje stawkę VAT, wykrywa
duplikaty semantyczne (np. ten sam abonament fakturowany dwa razy w
różnych formatach).

**Dlaczego BiLLU:** redukuje pracę księgowej z księgowania faktur
kosztowych klienta. Plus chat-asystent dla biura: „Pokaż mi klientów
których VAT-7 wciąż nie złożony za październik".

**Dostawcy:**
- **Anthropic Claude** (Sonnet 4.6 / Haiku 4.5) — najlepszy w analizie
  polskich tekstów + tańszy niż GPT-4
- **OpenAI GPT-4o** — alternatywa, droższa, dłuższy context
- **Mistral Large** — europejski (RODO-friendly), słabszy w polskim

**Koszt:** dla 1000 faktur/m-c klasyfikacja ≈ 30-50 zł.

**Trudność:** ⭐⭐⭐ — LLM API proste, ale prompt engineering + ewaluacja
(„czy to dobrze poklasyfikowało?") wymaga pracy.

**Plan techniczny:**
- service `AiClassifierService::classifyExpense($invoice)` zwraca
  `[category, vat_rate, confidence, reasoning]`
- nowa kolumna `invoices.ai_category` (suggestion) + `ai_accepted_by`
  (księgowa zatwierdza)
- async — kolejka `ai_classification_queue`, worker w cron co 5 min
- UI: w batch_detail badge „AI: koszt biurowy 23%"

---

### 3.3. BaseLinker / Allegro / Shopify / WooCommerce

**Co robi:** automatyczne wystawianie faktur do zamówień ze sklepu
internetowego klienta — bez kopiowania ręcznego.

**Dlaczego BiLLU:** dla biur obsługujących e-commerce klientów to jest
killer feature. Dziś klient eksportuje CSV ze sklepu, biuro
importuje — BaseLinker robi to przez API w czasie rzeczywistym.

**Strategia:** zacząć od **BaseLinker** — to centralny hub w polskim
e-commerce, łączy ~20 marketplaces (Allegro, Amazon PL, eBay,
Shopify, WooCommerce, PrestaShop, IdoSell, Shoper). Jedna integracja
zamiast 20.

**API:** **BaseLinker REST** — token, JSON, dobra dokumentacja.

**Trudność:** ⭐⭐ — średnia. API stabilne, mapping pól z BaseLinker
do `IssuedInvoice` to ~200 linii.

**Koszt:** BaseLinker free dla < 100 zamówień/m-c, potem 50 zł/m-c.

**Plan techniczny:**
- service `BaseLinkerService::pullOrders($clientId, $sinceDate)`
- migracja `client_baselinker_config` (api_token_enc, last_pulled_at)
- worker `baselinker-sync.php` co 15 min
- mapping: `order` → `IssuedInvoice` (auto-create, status='draft' żeby
  klient zatwierdził przed wystawieniem)

---

### 3.4. SMS / WhatsApp Business

**Co robi:** SMS-y przypominające o terminach płatności, WhatsApp z
linkiem do faktury, powiadomienia o nowych dokumentach od KAS.

**Dlaczego BiLLU:** open-rate SMS = 98%, email = 22%. Dla
przypomnień o ZUS / PIT / faktur niezapłaconych SMS jest
nieproporcjonalnie skuteczny.

**Dostawcy:**
- **SMSAPI.pl** — polski, najtańszy (~6 gr/SMS), prosta REST API
- **Twilio** — globalny, drogi (35 gr/SMS), ale daje też WhatsApp
- **Vonage** — alternatywa dla Twilio

**WhatsApp** wymaga zatwierdzenia template-message przez Meta i numeru
firmowego — uciążliwe ale działa dla wielu klientów.

**Trudność:** ⭐ (SMS) — ⭐⭐⭐ (WhatsApp).

**Plan techniczny:**
- service `SmsService` z adapterami `SmsApiAdapter`, `TwilioAdapter`
- nowa kolejka `sms_queue` (analog. `mail_queue`)
- UI: w preferencjach klienta nowe checkbox'y „SMS — przypomnienie
  o ZUS na 3 dni przed terminem"
- audit + opt-in compliance (RODO art. 7 — prosta zgoda)

---

### 3.5. Sentry + APM (observability)

**Co robi:** Sentry zbiera błędy PHP w produkcji, agreguje je, alertuje
gdy nowy typ błędu się pojawia. APM (Datadog / New Relic / Grafana
Tempo) mierzy czasy zapytań DB, czas response endpointów, anomalies.

**Dlaczego BiLLU:** dziś `error_log` lądują w plikach na dysku Plesk —
nikt ich nie czyta. Sentry → email alert + dashboard → szybkie
zauważenie regresji po deploy.

**Koszt:** Sentry free do 5k events/m-c, potem $26/m-c. Datadog drogie
(~$31/host/m-c). **Grafana Cloud free tier** wystarcza dla mid-size.

**Trudność:** ⭐ — instalacja `sentry/sentry-php` przez composer +
1 linia init w `public/index.php`. Pół godziny.

**Plan techniczny:**
- composer: `sentry/sentry: ^4.0`
- init w `public/index.php` po `Cache::init`
- `.env`: `SENTRY_DSN=` (puste = wyłączone, fail-open)
- prywatne dane redacted — sentry konfiguruje się żeby nie wysyłał
  `$_POST['password']`, `csrf_token`, etc.

---

## 4. Niski priorytet (nice-to-have, 12+ miesięcy)

### 4.1. Cloud storage — Google Drive / Dropbox / OneDrive

**Wartość:** alternatywa dla SFTP biura — niektórzy klienci/biura wolą
Drive/Dropbox bo mają to już skonfigurowane na firmie.

**Zakres:** rozszerzenie `SftpUploadService` o adaptery
`GoogleDriveAdapter`, `DropboxAdapter`, `OneDriveAdapter`. Wspólny
interface `RemoteStorageAdapter`. UI: dropdown "Dostawca" w `/office/sftp`.

**Trudność:** ⭐⭐ — OAuth2 dance jest podobny dla wszystkich.

---

### 4.2. KYC / weryfikacja tożsamości — Onfido / Veriff / Autenti iAuth

**Wartość:** dla biur obsługujących klientów-osoby fizyczne (JDG)
weryfikacja dowodu osobistego online + selfie zamiast spotkania.
Spełnia wymogi AML/przepisy o przeciwdziałaniu praniu pieniędzy.

**Zakres:** nowa tabela `kyc_verifications`, service `KycService`,
status `pending/verified/failed` jako warunek aktywacji konta klienta.

**Trudność:** ⭐⭐⭐ — webhooki + dokumenty wymagają długiego TTL na ich
storage z RODO compliance.

**Koszt:** Onfido ~3 €/check, Veriff ~2 €/check.

---

### 4.3. CEPiK — rejestr pojazdów

**Wartość:** wąskie zastosowanie — biura obsługujące **firmy flotowe**
(transport, taxi, leasing). Pozwala weryfikować rejestrację, OC, badania
techniczne pojazdów wpisanych jako środki trwałe.

**API:** **CEPiK 2.0** — wymaga umowy z MSWiA, niełatwo uzyskać dostęp.

**Trudność:** ⭐⭐⭐⭐ — głównie biurokratyczna.

---

### 4.4. Pełna migracja z legacy ERP (import z Comarch / Symfonia / Subiekt)

**Wartość:** akwizycja klientów którzy przechodzą z innych systemów —
"pokaż jak prosto zrobimy migrację twoich 5 lat danych".

**Zakres:** importery dla popularnych formatów:
- Comarch Optima (XML, MSSQL backup, Comarch ERP API jeśli klient ma)
- Symfonia (BSC export)
- InsERT Subiekt GT/Nexo (XML, MSSQL backup)
- Wapro Mag (DBF/CSV)
- Rachmistrz (XML)

**Trudność:** ⭐⭐⭐ per-format. Każdy ma quirki.

---

### 4.5. Slack / Microsoft Teams notifications

**Wartość:** zespół biura księgowego dostaje natychmiastowe powiadomienia
w kanale Slack o ważnych eventach (klient zaakceptował fakturę z błędem,
KAS przysłał wezwanie, faktura > 100k zł).

**Zakres:** rozszerzenie `WebhookService` o `SlackAdapter`,
`TeamsAdapter`. Jeden config w `/office/settings`.

**Trudność:** ⭐ — webhooki Slack/Teams są banalne.

---

### 4.6. Pushover / Pushbullet (mobile push)

**Wartość:** push-notification na telefon księgowej dla pilnych spraw
(np. faktura klienta przekroczyła limit kredytowy).

**Trudność:** ⭐. Integracja w 2 godziny.

---

### 4.7. NFZ / e-Pacjent

**Wartość:** wąskie — tylko biura obsługujące **podmioty medyczne**.
Pozwala księgować rozliczenia z NFZ za usługi.

**Trudność:** ⭐⭐⭐⭐ — bardzo specjalistyczne, mało dokumentacji.

---

### 4.8. eIDAS / Profil Zaufany

**Wartość:** alternatywne logowanie do panelu klienta przez Profil
Zaufany — klient nie musi pamiętać hasła do BiLLU jeśli ma już PZ.

**Trudność:** ⭐⭐⭐ — wymaga rejestracji w SOZE jako Service Provider.

---

### 4.9. AWS S3 / Wasabi / Backblaze — backup offsite

**Wartość:** kompletny backup BiLLU storage + DB dump na zewnętrznym
storage. Disaster recovery.

**Zakres:** rozszerzenie `cron.php` step 8 o upload do S3 z encryption
at rest. Wymaga `aws/aws-sdk-php`.

**Trudność:** ⭐ — proste API, dobrze udokumentowane.

**Koszt:** Wasabi $7/TB/m-c (najtańszy), AWS S3 ~$23/TB/m-c.

---

### 4.10. InfoVeriti / BIK — credit scoring firmy

**Wartość:** przed wystawieniem faktury z odroczonym terminem
sprawdzić rating klienta. Anti-fraud + zarządzanie ryzykiem dla biura
księgowego.

**API:** **InfoVeriti** ma REST. **BIK** wymaga umowy + opłat
miesięcznych.

**Trudność:** ⭐⭐ — biznesowo bardziej niż technicznie.

---

## 5. Architektura

Każda nowa integracja powinna trzymać się tego samego wzorca, sprawdzonego
już w `KsefApiService`, `SigniusApiService`, `SftpUploadService`:

### 5.1. Plik / klasa

- `src/Services/{Name}Service.php` — pojedyncza klasa, statyczne metody
- jeden `Client` z Guzzle keep-alive (kosztowny init = jeden raz)
- `verifyWebhookSignature()` jeśli webhook — zawsze HMAC-SHA256 +
  `hash_equals` (constant time)

### 5.2. Konfiguracja

- `config/{name}.php` z `$envGet` dla każdego sekretu
- `.env.example` zawiera wszystkie klucze (z pustymi wartościami)
- secrety w DB (np. per-office API key) → szyfrowane przez
  `App\Core\Crypto::encrypt($plain, 'service.scope')` z per-call
  `$context` żeby ciphertext jednej usługi nie pasował do drugiej

### 5.3. Asynchroniczność

Każda integracja która opóźnia request użytkownika idzie do **kolejki +
worker w cron**:

- migracja `{name}_queue` z `status ENUM(pending, sending, sent, failed)`,
  `attempts`, `last_error`, `created_at`
- service `enqueue()` — szybkie INSERT, **NIGDY** nie otwiera socketu
- worker `{name}-worker.php` w cron co minutę, drain `processQueue($batch)`
  — wzór: `mail-worker.php`, `sftp-worker.php`
- `MAX_ATTEMPTS = 5`, exponential backoff w retry

### 5.4. Obserwability

- `AuditLog::log` przy każdym ważnym evencie (`*_dispatched`, `*_failed`,
  `*_received`)
- error_log dla błędów technicznych (z masking PII jak w `MailService`)
- `Sentry` (gdy zostanie wdrożony) automatycznie — tylko stack-trace,
  nie payload

### 5.5. Tenant isolation

- każdy endpoint webhook **musi** być poza `requireClient/Office` ale
  `verifyWebhookSignature` chroni przed forge
- per-office config siedzi w `offices.{name}_*` szyfrowane (jak SFTP)
- per-client opt-in w `clients.{name}_enabled` jeśli dotyczy
  użytkowników końcowych (jak `sftp_push_*`)

### 5.6. Testy

- każda nowa integracja = `tests/Security/{Name}Test.php` z asercjami
  na: HMAC verification, length-gate na tokenach, FILLABLE bez
  sekretów, `verifyXyz` przed pierwszą mutacją w controllerze webhooka

---

## 6. Kolejność wdrożeń (proponowana)

Sekwencja optymalizująca **wartość biznesową / wysiłek**:

1. **KRS API + CRBR** (1-2 dni) — najtańsze, da od razu lepsze dane spółek
2. **Sentry + APM** (1 dzień) — fundament observability dla pozostałych
3. **OCR faktur** (1 tydzień) — Mindee, biggest time saver dla księgowej
4. **PSD2 / Open Banking** (3-4 tygodnie) — wymaga licencji/pośrednika,
   więc równolegle z legalnym uzgodnieniem
5. **Płatności linkiem** (1 tydzień) — Przelewy24, dobrze udokumentowane
6. **AI klasyfikacja** (2 tygodnie) — bazuje na #3, redukuje pracę księgowej
7. **ZUS PUE** (3 tygodnie) — najtrudniejsze państwowe API, ale pełna
   automatyzacja kadr
8. **e-Urząd Skarbowy** (3 tygodnie) — j.w.
9. **BaseLinker / Allegro** (2 tygodnie) — gdy będziesz mieć klientów
   e-commerce
10. **Reszta z sekcji 4** — w miarę potrzeb biznesowych

Łącznie **~4 miesiące pracy seniora full-time** dla pozycji 1-8 = pełny
"komplet" funkcjonalny pokrywający 95 % typowych potrzeb biura
księgowego.

---

## 7. Co zostawić poza zakresem (świadomie)

- **Własne procesowanie podpisów kwalifikowanych** — używamy SIGNIUS,
  nie chcemy budować własnego silnika XAdES/PAdES.
- **Własny DMS / archiwum dokumentów >5 lat** — to robota dla osobnego
  produktu, BiLLU pozostaje aplikacją operacyjną. Backup → S3 wystarczy.
- **CRM dla biura** — to inny rynek (Pipedrive / HubSpot). Integracja
  „export klientów do CRM" tak, ale własny CRM nie.
- **Księga główna / pełna księgowość** — BiLLU jest **operacyjnym
  systemem dla biur** (faktury + kadry + komunikacja z klientem),
  nie pełnym ERP. Wymiana danych z istniejącymi ERP (Comarch / Symfonia)
  jest zaadresowana w sekcji 4.4.

---

*Dokument żywy — aktualizuj przy każdej decyzji o nowej integracji
albo deprecation istniejącej.*
