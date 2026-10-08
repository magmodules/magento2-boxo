# Configuratiegids

Hier vind je alle instellingen van [BOXO Herbruikbare Verpakking](https://www.magmodules.nl/magento2-boxo-reusable-packaging.html). Deze gids legt uit wat elke optie doet en wanneer je hem zou gebruiken, in dezelfde indeling als het admin-panel zodat je snel vindt wat je zoekt. De instellingen die bepalen wat een klant betaalt — het waarborgbedrag voor herbruikbaar en de toeslag voor eenmalig — behandelen we het uitgebreidst, samen met de productregels die bepalen welke carts de herbruikbare optie überhaupt krijgen.

Complete referentie van alle configuratie-opties van BOXO Herbruikbare Verpakking.

**Locatie:** Winkels → Configuratie → BOXO → Herbruikbare verpakking

## Algemeen

### API-sleutel

Je sleutel van BOXO. Hij wordt versleuteld opgeslagen en nooit volledig aan je teruggegeven.

### Verbinding testen

Controleert de sleutel bij BOXO en vertelt je wat hij aantrof. Hij test wat er op dat moment in het veld API-sleutel staat, dus je kunt een sleutel verifiëren voordat je hem opslaat.

De drie antwoorden betekenen verschillende dingen, en het is nuttig te weten welk antwoord welk is:

- **Verbinding tot stand gebracht** — de sleutel werkt
- **Sleutel geweigerd** — BOXO antwoordde, en zei nee. Controleer de sleutel, of of hij al geactiveerd is
- **API niet bereiken** — er antwoordde niets. Een firewall, een uitgaande proxy of een storing. Je sleutel is waarschijnlijk in orde

## Checkout

### Standaardverpakking

Welke optie voorgeselecteerd staat als de verpakkingssectie verschijnt. Herbruikbaar staat standaard voor, en dat is precies het punt van de module — de klant moet er actief voor kiezen om het níet te doen.

**Wanneer gebruiken:** Zet het op eenmalig als je herbruikbare verpakking voorzichtig introduceert en liever hebt dat klanten er bewust voor kiezen dan dat ze het al aangevinkt aantreffen.

### Toegestane producten

Bepaalt welke producten in herbruikbare verpakking verzonden mogen worden.

- **Alle producten** — alles komt in aanmerking, er wordt geen lijst geraadpleegd
- **Alle producten behalve de geselecteerde** — alles komt in aanmerking, behalve wat je aanvinkt
- **Alleen de geselecteerde producten** — niets komt in aanmerking tenzij je het aanvinkt

De lijst zit op het product zelf, als een checkbox onder de BOXO-sectie: *Uitsluiten van BOXO herbruikbare verpakking* voor de eerste modus, *Toestaan in BOXO herbruikbare verpakking* voor de tweede. Het zijn bewust twee losse velden, zodat je je uitsluitingen kunt vastleggen, een tijdje *Alleen de geselecteerde producten* kunt proberen, en weer terug kunt zonder je lijst kwijt te zijn.

**Eén niet-toegestaan product haalt de herbruikbare optie voor de hele cart weg.** De order wordt als één pakket verzonden, dus het strengste product erin bepaalt het. Een klant die één te groot artikel toevoegt, ziet de herbruikbare optie verdwijnen.

**Wanneer gebruiken:**
- *Alle producten* bij een catalogus met grotendeels vergelijkbare artikelen
- *Alle producten behalve de geselecteerde* als een handvol producten te groot, te zwaar of te onhandig is
- *Alleen de geselecteerde producten* als je herbruikbare verpakking op een deel van de catalogus wilt piloten

### Maximum aantal producten

Het grootste aantal artikelen dat nog in één herbruikbare container past. Laat het op 0 voor geen limiet.

Dit telt het totale aantal stuks, niet het aantal cartregels: vijf keer hetzelfde product vult de doos net zo goed als vijf verschillende. Het verpakkingsartikel zelf wordt niet meegeteld, en de regels van een configurable of bundle product worden via hun parent geteld in plaats van dubbel.

**Wanneer gebruiken:** Stel het in zodra je weet wat er echt in past. Een shop met kleine artikelen heeft het misschien nooit nodig; een schoenenwinkel wel.

### Toeslag inschakelen en Bedrag van de toeslag

Of eenmalige verpakking de klant iets kost, en hoeveel.

Laat de toeslag uit en eenmalige verpakking wordt gratis aangeboden. Er komt dan helemaal geen regel in de cart — de klant ziet geen bedrag van nul in het besteloverzicht, en zijn keuze wordt nog steeds op de order vastgelegd.

Zet je hem aan, dan wordt het bedrag als een normale, belaste orderregel gerekend, volgens de belastinginstellingen van je store.

**Wanneer gebruiken:** Een kleine toeslag is de gebruikelijke manier om herbruikbare verpakking de aantrekkelijke optie te maken zonder eenmalig helemaal te weigeren.

### URL van informatiepagina

Waar het info-icoon naast de herbruikbare optie naartoe linkt. Laat het leeg en klanten komen op de BOXO-pagina met inleverpunten, wat voor de meeste shops precies goed is.

**Wanneer gebruiken:** Verwijs naar je eigen pagina als je je retourproces in je eigen woorden uitlegt.

## Teksten in de checkout

De tekst die klanten in de checkout lezen is niet instelbaar, en dat is bewust: de formulering hoort bij de BOXO-propositie en moet in elke shop hetzelfde lezen. Heb je hem in een andere taal nodig, vertaal hem dan via een locale-CSV in plaats van hem per store opnieuw te typen — zo blijft een meertalige shop echt gelokaliseerd in plaats van dat één getypte string in elke taal vastgezet wordt.

## Debug & logging

### Debug-modus

Schrijft API-aanroepen, beschikbaarheidschecks en selectie-updates naar `var/log/boxo-debug.log`. Fouten worden altijd naar `var/log/boxo-error.log` geschreven, of debug-modus aan staat of niet.

Zet hem aan **voordat** je een probleem reproduceert. Hij kan niet terughalen wat een eerdere run zou hebben geschreven.

### Debug- en foutencontrole

Toont de laatste regels van een van beide logs in een modal, zodat je ze kunt bekijken zonder shell-toegang.

### Selftest

Doet een reeks controles en rapporteert ze op één plek: je PHP-versie, je Magento-versie, of de module aan staat, of de twee BOXO-verpakkingsproducten bestaan en verkocht kunnen worden, of de API antwoordt, en op welke versie van de extensie je zit.

Het is de snelste eerste stap als iets niet werkt, omdat het "de module is verkeerd ingesteld" onderscheidt van "de module is in orde en er is iets anders aan de hand".

## Wat de module rekent

Bij installatie worden twee producten aangemaakt:

| Wat | Prijs | Btw |
|---|---|---|
| Waarborg herbruikbare verpakking | 3,95 | Geen — een waarborg is geen verkoop, dus geen btw |
| Eenmalige verpakking | Jouw toeslag, of gratis | Belast, volgens de belastingregels van je store |

Het waarborgbedrag wordt door BOXO bepaald, niet door jou. De toeslag is van jou.

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-boxo-reusable-packaging.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support) - Hulp van ons team
