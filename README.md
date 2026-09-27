# Moodle – grunnoppsett

Moodle er en læringsplattform: **Lærere** lager kurs med fagstoff, oppgaver og quizer; **deltakere** kan lære og levere arbeid. Dette repoet setter opp Moodle LMS 5.2.3 uten forhåndsopprettede kurs, men med en innloggingsklar `demo`-bruker. Moodle-kjernen hentes ved bygging, mens `plugins/local/` er avsatt til egne tillegg som utvider Moodle.

## Kom i gang med Docker

Du trenger Docker med Compose og `curl`. Kjør fra repoets rotmappe:

```sh
./bin/setup
```

Åpne <http://localhost:8080> og logg inn som **demo** med **Demo123!**. På en ny installasjon er dette en vanlig bruker uten kurs eller lærerrolle. For administrasjon: logg inn som **admin** med `ADMIN_PASSWORD` fra den Git-ignorerte filen `.env`. Databasepassordet opprettes i samme fil. Norsk bokmål er standardspråk, også for eksisterende engelske kontoer første gang oppsettet kjøres. Logg ut og inn igjen hvis en aktiv økt fortsatt vises på engelsk. Tjenesten er bare tilgjengelig lokalt på `127.0.0.1`; database og opplastede filer lagres i Docker-volumer.

```sh
docker compose stop         # Stopp uten å slette data
docker compose up -d        # Start igjen
docker compose logs -f cron # Se bakgrunnsjobber
./bin/setup                # Bygg på nytt etter endringer i plugins/local/
docker compose down -v     # Slett database og filer; kjør deretter ./bin/setup
```

Du kan kjøre `./bin/setup` flere ganger uten å miste eksisterende data. **Cron** kjører planlagte Moodle-oppgaver, som varsler og opprydding, hvert minutt.

Etter lokalt oppsett kan du kjøre `phpunit --configuration tests/phpunit.xml` med PHPUnit 11. Testene dekker innlogging, norsk språk, kursvisning og redigering; et midlertidig testkurs slettes etterpå. GitHub Actions tester også database, cron og gjentatt oppsett ved push og pull request.

Har du allerede data fra et tidligere oppsett, beholdes de når du kjører `./bin/setup` igjen. Bruk reset-kommandoen over hvis du vil starte helt på nytt; den sletter alle eksisterende data.

Ved flytting bak en reverse proxy på VPS må `MOODLE_WWWROOT` i `.env` settes til adressen brukerne faktisk besøker, og `MOODLE_PORT` til en ledig lokal port. Med `https://` i `MOODLE_WWWROOT` stoler Moodle på at proxyen terminerer TLS. Webserver, domene og TLS på VPS må settes opp separat.

## Vanlig installasjon uten Docker

Denne veien installerer Moodle direkte på en server, uten Docker-oppsettet eller `demo`-brukeren i repoet.

1. Last ned [Moodle 5.2.3](https://download.moodle.org/download.php/stable502/moodle-5.2.3.tgz) og installer en webserver, PHP 8.3 eller 8.4 med [nødvendige utvidelser](https://docs.moodle.org/502/en/Installation_Quickstart), og PostgreSQL 16.
2. Opprett en tom database med egen databasebruker og en skrivbar `moodledata`-mappe **utenfor** webområdet. Pakk ut Moodle og la webserveren peke på Moodles `public/`-mappe. [Sett opp ruting](https://docs.moodle.org/502/en/Configuring_the_Router) til `r.php`.
3. Åpne nettstedet i nettleseren og følg installasjonsveiviseren. Oppgi database, `moodledata` og administratorbruker. Installer språkpakken **Norsk bokmål** (`nb`), velg den som standardspråk og slå av automatisk språkvalg fra nettleseren. Sett også norsk som foretrukket språk for eksisterende brukere.
4. Kjør `admin/cli/cron.php` som webserverens bruker hvert minutt. Se [Moodles installasjonsguide](https://docs.moodle.org/502/en/Installation_Quickstart) for et eksempel på cron-oppsett.
