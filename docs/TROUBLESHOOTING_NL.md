# Probleemoplossing

Loop je tegen problemen aan met [BOXO Herbruikbare Verpakking](https://www.magmodules.nl/magento2-boxo-reusable-packaging.html)? Deze pagina behandelt de problemen die het vaakst voorkomen, te beginnen met een korte diagnose waarmee je bepaalt in welke helft van het systeem je moet zoeken. Daarna staat per probleem wat je zou zien, hoe je het oplost, en hoe je voorkomt dat het terugkomt. De laatste sectie legt uit hoe je het debug-log leest, wat de meeste vragen sneller antwoordt dan gokken.

Veelvoorkomende problemen en oplossingen voor BOXO Herbruikbare Verpakking.

## Snelle diagnose

Werk deze op volgorde af voordat je verder zoekt:

1. Draai de **Selftest** onder Debug & logging. Die controleert de modulestatus, de verpakkingsproducten en de API-verbinding in één keer
2. Controleer dat **Inschakelen** op *Ja* staat voor de storefront die je test
3. Klik op **Verbinding testen** om te bevestigen dat de API-sleutel geaccepteerd wordt
4. Controleer dat het verzendland Nederland is en de postcode echt bestaat
5. Leeg de cache
6. Zet Debug-modus aan, reproduceer het probleem, en lees `var/log/boxo-debug.log`

---

## Veelvoorkomende problemen

### Probleem: Er verschijnen geen verpakkingsopties in de checkout

**Symptomen:**
- De checkout ziet er precies uit als voor de installatie van de module
- Nergens een foutmelding

**Oplossing:**
1. Controleer dat **Inschakelen** op *Ja* staat. De module staat standaard uit
2. Controleer dat het verzendadres in Nederland ligt. Andere landen worden overgeslagen zonder de API aan te roepen
3. Controleer dat de postcode geldig is — vier cijfers die niet met nul beginnen, dan twee letters
4. Zet Debug-modus aan en reproduceer. Het log noemt de reden, inclusief "reusable packaging not offered" met het product of aantal dat het veroorzaakte
5. Toets de cart aan je regels onder **Toegestane producten**

**Voorkomen:** Plaats een testorder na elke wijziging in de productregels. Eén nieuw uitgesloten product is genoeg om de optie voor een hele cart weg te halen.

---

### Probleem: De herbruikbare optie verdween bij de cart van één klant

**Symptomen:**
- Bij sommige carts verschijnen de opties en bij andere niet
- De klant zegt dat het gisteren wel werkte

**Oplossing:**
1. Kijk wat er in de cart zit. Eén niet-toegestaan product haalt de herbruikbare optie voor de hele order weg
2. Tel de artikelen. Staat **Maximum aantal producten** ingesteld en is het totaal hoger, dan wordt de optie niet aangeboden
3. Het debug-log legt beide gevallen vast met het exacte product of aantal

**Voorkomen:** Dit is de module zoals bedoeld. Gebeurt het vaker dan je verwacht, dan is je uitsluitingslijst of je limiet strenger dan je bedoelde.

---

### Probleem: Verbinding testen zegt dat de sleutel geweigerd is

**Symptomen:**
- "De BOXO API heeft deze sleutel geweigerd"

**Oplossing:**
1. Controleer de sleutel op een meegekopieerde spatie of een ontbrekend teken
2. Bevestig bij BOXO dat de sleutel actief is
3. Bevestig dat je de sleutel gebruikt die voor deze shop is uitgegeven

**Voorkomen:** Gebruik Verbinding testen elke keer dat je de sleutel wijzigt. Hij test wat er in het veld staat, dus je weet het vóór het opslaan.

---

### Probleem: Verbinding testen kan de API niet bereiken

**Symptomen:**
- "Kon de BOXO API niet bereiken, bekijk het foutenlog voor details"

**Oplossing:**
1. Kijk in `var/log/boxo-error.log` voor de onderliggende melding
2. Controleer dat uitgaand HTTPS vanaf de shop is toegestaan. Hosting met een uitgaande firewall of proxy blokkeert dit vaak
3. Probeer het over een paar minuten opnieuw — zo ziet een storing bij BOXO er ook uit

**Voorkomen:** Niets in de module veroorzaakt dit. Vraag je hostingpartij uitgaand verkeer naar de BOXO API permanent toe te staan.

---

### Probleem: Een optie kiezen geeft een foutmelding

**Symptomen:**
- Een melding onder de verpakkingsopties dat het verpakkingsproduct niet beschikbaar is in deze store
- De selectie blijft niet staan

**Oplossing:**
1. Draai de Selftest en bekijk de controle op verpakkingsproducten
2. Open de twee producten via Catalogus → Producten. Ze worden bij installatie aangemaakt en zijn niet los zichtbaar
3. Controleer dat ze aan de website hangen die je test, aan staan, en voorraad hebben in de source die die website bedient
4. Ontbreken ze helemaal, dan zijn de setup-scripts van de module niet op deze installatie gedraaid — vraag je developer of hostingpartij de module-upgrade af te ronden

**Voorkomen:** Controleer de verpakkingsproducten na het toevoegen van een nieuwe website. Producten die alleen aan de oorspronkelijke website hangen kunnen op de nieuwe niet in een cart gezet worden.

---

### Probleem: Eenmalige verpakking wordt niet gerekend

**Symptomen:**
- De klant kiest eenmalig en betaalt er niets voor
- Geen verpakkingsregel in de order

**Oplossing:**
1. Controleer dat **Toeslag inschakelen** op *Ja* staat
2. Controleer dat **Bedrag van de toeslag** hoger is dan nul

Staat de toeslag uit, dan is dit correct gedrag: de optie is gratis en voegt bewust geen orderregel toe.

**Voorkomen:** Wil je hem wel rekenen, controleer dat dan op een testorder en niet alleen op het configuratiescherm.

---

### Probleem: De gekozen verpakking ontbreekt in de order grid

**Symptomen:**
- De kolom is leeg bij sommige orders

**Oplossing:**
1. Orders van vóór de installatie hebben geen waarde en tonen terecht een lege cel
2. Controleer bij recente orders of de kolom aan staat in de kolomkiezer van de grid
3. Toont de orderpagina wel een keuze en de grid niet, herindexeer dan de sales order grid

**Voorkomen:** Voor historische orders is er niets te voorkomen — die cel blijft leeg, zo bedoeld.

---

### Probleem: Verpakking verschijnt op een in-store pickup order

**Symptomen:**
- Een pickup-order heeft een verpakkingsregel

**Oplossing:**
1. Bevestig dat de order echt in-store pickup gebruikte en niet een verzendmethode die er zo heet
2. Kijk in het debug-log rond de verzendstap
3. Meld het met het order increment id — de module haalt de verpakkingsregel weg zodra pickup gekozen wordt, dus dit hoort niet te gebeuren

**Voorkomen:** Dit is geen verwacht gedrag en verdient een supportticket in plaats van een workaround.

---

## Debug-modus

Zet hem aan via Winkels → Configuratie → BOXO → Herbruikbare verpakking → Debug & logging.

Er worden twee logs weggeschreven:

- `var/log/boxo-debug.log` — API-verzoeken en -antwoorden, beschikbaarheidschecks, selectiewijzigingen, en de reden waarom de herbruikbare optie niet is aangeboden. Alleen als Debug-modus aan staat
- `var/log/boxo-error.log` — fouten. Altijd

Beide zijn vanuit de admin te lezen met de knoppen **Debug-controle** en **Foutencontrole**, dus je hebt geen shell-toegang nodig.

Waar je op let:

- `checkServiceAvailable request` en het antwoord erop, met de status die BOXO teruggaf — dit vertelt je of de API antwoordde en wat hij zei
- `reusable packaging not offered` met een product id of een aantal — dit zijn de regels die hun werk doen, en het noemt de oorzaak
- `api_key_masked` toont alleen de eerste en laatste vier tekens van je sleutel. Dat is opzet; de volledige sleutel wordt nooit gelogd

Zet Debug-modus weer uit als je klaar bent. Op een drukke shop groeit het log snel en leest niemand het.

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-boxo-reusable-packaging.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support) - Hulp van ons team
