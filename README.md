# Moodle – grunnoppsett

Moodle er en læringsplattform: **Lærere** lager kurs med fagstoff, oppgaver og quizer; **deltakere** kan lære og levere arbeid. Dette repoet setter opp Moodle LMS 5.2.3 uten forhåndsopprettede kurs, men med en innloggingsklar `demo`-bruker. Moodle-kjernen hentes ved bygging, mens `plugins/` inneholder egne tillegg. Værblokken på dashbordet viser været nå, symboler for gjenværende deler av dagen og samlet prognose for resten av dagen i Kristiansand.

## Kom i gang med Docker

Du trenger Docker med Compose og `curl`. Kjør fra repoets rotmappe:

```sh
./bin/setup
```

Åpne <http://localhost:8080> og logg inn som **demo** med **Demo123!**. På en ny installasjon er dette en vanlig bruker uten kurs eller lærerrolle. Nye brukere kan velge **Opprett ny konto** og bekrefte adressen sin via e-post. Lokalt finner du bekreftelsesbrevet i [Mailpit](http://localhost:8025); det sendes ikke ut på internett. For administrasjon: logg inn som **admin** med `ADMIN_PASSWORD` fra den Git-ignorerte filen `.env`. Databasepassordet opprettes i samme fil. Norsk bokmål er standardspråk, også for eksisterende engelske kontoer første gang oppsettet kjøres. Logg ut og inn igjen hvis en aktiv økt fortsatt vises på engelsk. Nettsiden og Mailpit er bare tilgjengelige lokalt på `127.0.0.1`; database, opplastede filer og testbrev lagres i Docker-volumer.

```sh
docker compose stop         # Stopp uten å slette data
docker compose up -d        # Start igjen
docker compose logs -f cron # Se bakgrunnsjobber
./bin/setup                # Bygg på nytt etter endringer i plugins/
docker compose down -v     # Slett database og filer; kjør deretter ./bin/setup
```

Du kan kjøre `./bin/setup` flere ganger uten å miste eksisterende data. **Cron** kjører planlagte Moodle-oppgaver, som varsler og opprydding, hvert minutt.

Værblokken mellomlagrer én felles prognose fra MET Norway til API-et ber om ny henting. Symbolene er kopiert fra [Laravel Yr](https://github.com/ekstremedia/laravel-yr) med [MIT-lisens](plugins/blocks/weather/pix/symbols/LICENSE). `YR_USER_AGENT` i `.env` identifiserer installasjonen; sett den til ditt nettsted eller en kontaktadresse på VPS, og behold anførselstegnene. Blokken lenker til [hele varselet på Yr](https://www.yr.no/nb/v%C3%A6rvarsel/daglig-tabell/1-2376/Norge/Agder/Kristiansand/Kristiansand). Ved installasjon uten Docker kopierer du `plugins/blocks/weather` til Moodles `public/blocks/weather`, kjører oppgradering og legger blokken på dashbordet som administrator.

Etter lokalt oppsett kan du kjøre `phpunit --configuration tests/phpunit.xml` med PHPUnit 11. Testene dekker innlogging, registrering, norsk språk, kurs, værblokken og læringssteg. Testbrukeren og testkurset slettes etterpå. GitHub Actions tester også database, cron og gjentatt oppsett ved push og pull request.

Har du allerede data fra et tidligere oppsett, beholdes de når du kjører `./bin/setup` igjen. Bruk reset-kommandoen over hvis du vil starte helt på nytt; den sletter alle eksisterende data.

Ved flytting bak en reverse proxy på VPS må `MOODLE_WWWROOT` i `.env` settes til adressen brukerne faktisk besøker, og `MOODLE_PORT` til en ledig lokal port. Med `https://` i `MOODLE_WWWROOT` stoler Moodle på at proxyen terminerer TLS. Webserver, domene og TLS på VPS må settes opp separat.

For at registreringsbrev skal nå brukerne, fyll inn SMTP-verdiene i `.env` og kjør `./bin/setup` igjen. Bruk gjerne de samme verdiene som i Laravel:

```dotenv
MAIL_HOST=smtp.example.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=<SMTP-passord>
MAIL_FROM_ADDRESS=noreply@example.com
```

`MAIL_HOST=mailpit` er bare for lokal testing. Etter oppsett kan administrator sende et prøvebrev fra **Nettstedsadministrasjon → Server → E-post → Utgående e-post**. Nye kontoer får ingen kurs automatisk; en lærer eller administrator må melde dem på, eller aktivere selvpåmelding i et kurs.

## Et lite læringssteg

Dashbordet har fem korte øvelser på bokmål om tilgjengelig kursinnhold: lenker, bilder, overskrifter, video og farge. Velg et svar, les forklaringen og gå videre når det passer. Fremdriften huskes mellom innlogginger og kan nullstilles. Dette er øving uten karakterer; oppgavene er eksempler, ikke en fullstendig WCAG-sjekkliste.

Kjør `./bin/setup` etter at pluginen er hentet; dette bygger bildet, oppgraderer Moodle og legger blokken på eksisterende og nye dashbord. Uten Docker kopierer du `plugins/blocks/learningstep` til `public/blocks/learningstep`, kjører Moodle-oppgradering og legger blokken på standarddashbordet. Oppgavene følger pluginen og redigeres foreløpig i språkfilene.

Blokken bruker vanlige skjemaer, synlige fokusmarkeringer og tekstlige tilbakemeldinger, og fungerer uten JavaScript. CI kjører også tastaturtester i Chromium og automatiske tilgjengelighetssjekker med axe. Disse erstatter ikke manuell testing med skjermleser. Kjør nettlesertestene mot en lokal **testinstallasjon** (de oppretter og sletter testbrukere):

```sh
npm install --no-save --package-lock=false @playwright/test@1.58.2 @axe-core/playwright@4.11.1
npx playwright install --with-deps chromium
npx playwright test --config tests/browser/playwright.config.cjs
```

## Vanlig installasjon uten Docker

Denne veien installerer Moodle direkte på en server, uten Docker-oppsettet eller `demo`-brukeren i repoet.

1. Last ned [Moodle 5.2.3](https://download.moodle.org/download.php/stable502/moodle-5.2.3.tgz) og installer en webserver, PHP 8.3 eller 8.4 med [nødvendige utvidelser](https://docs.moodle.org/502/en/Installation_Quickstart), og PostgreSQL 16.
2. Opprett en tom database med egen databasebruker og en skrivbar `moodledata`-mappe **utenfor** webområdet. Pakk ut Moodle og la webserveren peke på Moodles `public/`-mappe. [Sett opp ruting](https://docs.moodle.org/502/en/Configuring_the_Router) til `r.php`.
3. Åpne nettstedet i nettleseren og følg installasjonsveiviseren. Oppgi database, `moodledata` og administratorbruker. Installer språkpakken **Norsk bokmål** (`nb`), velg den som standardspråk og slå av automatisk språkvalg fra nettleseren. Sett også norsk som foretrukket språk for eksisterende brukere.
4. Under **Innlogging**, aktiver **E-postbasert egenregistrering** som autentiseringsmetode og velg den for egenregistrering. Legg inn SMTP-vert med port, sikkerhetstype, brukernavn, passord og avsenderadresse under **Server → E-post → Utgående e-post**.
5. Kjør `admin/cli/cron.php` som webserverens bruker hvert minutt. Se [Moodles installasjonsguide](https://docs.moodle.org/502/en/Installation_Quickstart) for et eksempel på cron-oppsett.
