# 📋 Faktury KSeF - System Weryfikacji Faktur

**Kompleksowy system zarządzania i weryfikacji faktur dla biur księgowych z integracją KSeF (Krajowy System e-Faktur).**

---

## 📑 Spis treści

- [Przegląd](#przegląd)
- [Funkcjonalności](#funkcjonalności)
- [Architektura](#architektura)
- [Stack technologiczny](#stack-technologiczny)
- [Wymagania systemowe](#wymagania-systemowe)
- [Instalacja](#instalacja)
- [Konfiguracja](#konfiguracja)
- [Użytkowanie](#użytkowanie)
- [Struktura projektu](#struktura-projektu)
- [Integracje API](#integracje-api)
- [Zadania cron](#zadania-cron)
- [Wsparcie](#wsparcie)

---

## 🎯 Przegląd

**Faktury KSeF** to nowoczesny system internetowy do zarządzania fakturami i ich weryfikacji, dedykowany polskim biurom księgowym. System integruje się z platformą KSeF (Krajowy System e-Faktur) w celu usprawnienia przetwarzania, weryfikacji i raportowania faktur.

### Główne cele
- ✅ Centralizacja weryfikacji i zarządzania fakturami
- ✅ Przetwarzanie partii faktur z integracją KSeF
- ✅ Obsługa wielu klientów z kontrolą dostępu opartą na rolach
- ✅ Automatyczne generowanie raportów i powiadomień
- ✅ Śledzenie ośrodków kosztów

---

## ✨ Funkcjonalności

### 🔐 System Autentykacji i Autoryzacji
- **Admin** - Pełna kontrola systemu i zarządzanie
- **Biuro** - Pracownicy biura księgowego
- **Klient** - Użytkownicy końcowi weryfikujący faktury
- **Master Login** - Możliwość personifikacji dla super-admina

### 📦 Zarządzanie Fakturami
- ✅ Przetwarzanie faktur w partiach
- ✅ Weryfikacja poszczególnych faktur
- ✅ Zbiorcze importy (Excel/CSV)
- ✅ Śledzenie statusów (oczekująca, zweryfikowana, odrzucona, zaakceptowana)
- ✅ Przypisywanie ośrodków kosztów
- ✅ Obsługa dokumentów XML, PDF

### 🌐 Integracja KSeF
- ✅ Bezpośrednie połączenie z API platformy KSeF
- ✅ Automatyczne przesyłanie partii
- ✅ Weryfikacja statusu w czasie rzeczywistym
- ✅ Generowanie i pobieranie raportów

### 👥 Zarządzanie Klientami
- ✅ Obsługa wielu klientów
- ✅ Ośrodki kosztów dla każdego klienta
- ✅ Hierarchia klientów
- ✅ Zbiorcze importy klientów

### 📊 Raporty i Eksporty
- ✅ Generowanie raportów PDF (TCPDF)
- ✅ Eksport do Excela (PhpSpreadsheet)
- ✅ Raporty odrzuceń
- ✅ Raporty śledzenia terminów

### 🔒 Bezpieczeństwo
- ✅ Haszowanie haseł (bcrypt)
- ✅ Zarządzanie sesjami
- ✅ Dziennik audytu wszystkich operacji
- ✅ Reset hasła przez email
- ✅ Śledzenie akceptacji polityki prywatności

### 📧 Powiadomienia
- ✅ Email dla nowych faktur
- ✅ Przypomnienia o terminach
- ✅ Zautomatyzowane powiadomienia (cron)
- ✅ Integracja SMTP (PHPMailer)

### 🔍 Weryfikacja Danych
- ✅ Integracja API GUS (polska baza firm)
- ✅ Weryfikacja numeru NIP
- ✅ Walidacja danych firmy

---

## 🏗️ Architektura

### Wzorzec MVC
System stosuje architekturę Model-View-Controller:
