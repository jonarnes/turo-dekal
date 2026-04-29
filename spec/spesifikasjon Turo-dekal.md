# spesifikasjon Turo-dekal
Dette dokumentet beskriver en webapp som skal produsere dekaler/merkelapper med QR-koder, opplastede bilder og annen info. Merkelappene skal være nedlastbare i a4 format, med 2 merkelapper pr ark (hver merkelapp/dekal er a5).

## webapp
Appen skal lages i php. bruk sqlite hvis behov. Bruk en enkel MVC. Ikke Laravel eller andre tunge rammeverk. Keep it simple.

## funksjonalitet

Appen skal imot (opplasting) et excelark som inneholder poster og metadata. Deriblant en QR kode. Denne er viktig og skal ha en sentral plass på dekalen. Annen info i exselfilen skal også vises på dekalen i tilknytning til QR koden. Postkoden er den nest-viktigste informasjonen.

Når filen er lastet opp kan brukeren laste ned dekalene som pdf for utskrift.

Dekalen skal ha tre kolonner på 5cm hver. kolonnene skal sentreres på a5. PDF utskriften skal være i a4 format. på hvert a4 ark skal det være 2 a5 utsnitt. ett per dekal. dekalene skal være i liggende a5 format.

Appen må ha en "konfigurasjonsmodus" der brukeren kan laste opp bilder som også skal plasseres på dekalen. Det skal være mulig å bestemme hvor (hvilke av de tre kolonnene på dekalen) bildene skal plasseres i. Lag gjerne en visuell drag and drop konfigurasjon av dette. Oppsettet skal kunne lagres og dekalene skrives ut som konfigurert.

PDFen med dekalen skal kunne skrives ut i sin helhet, eller valgte dekaler med koder fra excelfilen.


