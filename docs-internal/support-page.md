# Stránka podpory — návrh úprav

Stav k 2026-10-02 (`src/Admin/SupportPage.php`, `admin.php?page=wpify/support`). Implementováno 2026-10-02 (rozhodnutí: výchozí 3 logy, formulář nesbalený, tabulka pluginů se stavem licence).

## Účel stránky

Stránka má **snížit počet dotazů na podporu**: nejdřív provést uživatele častými problémy a dokumentací, teprve pak nabídnout formulář. Pořadí „svépomoc → formulář“ je záměr a zůstává.

## Co je dnes špatně

1. **Odkazy na dokumentaci jsou nejslabší místo cesty.** Bod 3 checklistu vypisuje dokumentaci každého aktivního pluginu pod sebe (lokálně 15 odkazů), bez verze a bez vazby na problém — uživatel je přeskočí. Karta je 2× vyšší než FAQ vedle, vzniká prázdné místo.
2. **Bod 4 posílá na e-mail** („napište nám na support@wpify.io“) — obchází formulář s diagnostikou i celou cestu svépomoci.
3. **Nepřeložený řetězec** „Check plugin documentation“ (chybí v překladu `wpify-core`).
4. **Formulář:** logy jako nativní multi-select s nápovědou Ctrl/Cmd, různé šířky polí, poznámka o diagnostice až pod tlačítkem a bez výpisu, co se posílá.
5. **Vizuál mimo komponenty core:** karty `wpify__card`, nadpisy h2/h3 stejně velké, hláška o odeslání je WP `.notice`.

## Návrh

### Rozvržení (pořadí zůstává: svépomoc → formulář)

1. **Než napíšete podpoře** — krátký úvod, proč projít kroky (většinu problémů řeší dokumentace a logy).
2. **Kroky svépomoci** jako číslované sekce (`.wpify-section`):
   1. Poznámky k objednávce.
   2. Logy — tlačítko na WPify Logs (jen když logy existují).
   3. **Dokumentace vašich pluginů** — tabulka aktivních WPify pluginů: název, verze, stav licence (`.wpify-badge`), odkaz na dokumentaci (`doc_link` z `wpify_installed_plugins`; samostatný odkaz na řešení problémů plugin dnes nedodává). Nahradí 15 holých odkazů.
3. **Časté dotazy** vedle kroků (jako dnes), filtr `wpify_dashboard_support_faqs` beze změny.
4. **Problém přetrvává?** — formulář až na konci. Varianta k rozhodnutí: formulář **sbalený** za tlačítkem „Napsat podpoře“ (rozbalí se na klik, po chybě odeslání / s `#support-form` v URL je rozbalený), aby se k němu uživatel dostal až po krocích.

Sidebar (newsletter, novinky) beze změny.

### Formulář

- Po výběru pluginu se nad zprávou ukáže „Prošli jste dokumentaci k …?“ s odkazem — poslední šance na svépomoc.
- Pole jednotné šířky (max ~640px), zpráva stejně široká.
- **Logy podle pluginu, ne po souborech.** Logů jsou desítky (lokálně 64: 9 uchovaných souborů × plugin) a vznikají jen ve dny s aktivitou, takže ani výběr „za N dní“ nefunguje. Po výběru pluginu: „Připojit poslední logy pluginu X“ (zaškrtávátko + počet 1 / 3 / všechny uchované, výchozí 3); soubory vybere server podle data v názvu. U „Obecné“ se logy nepřipojují (hint „Vyberte plugin…“). Ruční výběr konkrétních souborů jen v rozbalovacím „Vybrat konkrétní soubory“ — dnešní multi-select filtrovaný na plugin (max ~9 položek). Ověření proti dostupným logům (`get_log_attachment_paths`) beze změny.
- **Co se odešle automaticky** — rozbalovací `<details>` nad tlačítkem s výpisem diagnostiky.
- Po odeslání `.wpify-notice-success` / `.wpify-notice-error`.

### Obsah

- Bod „Kontaktujte podporu“ → odkaz na formulář níže; e-mail jen jako náhradní cesta, když formulář nejde odeslat.
- Doplnit překlad „Check plugin documentation“.
- FAQ: případně „Jak rychle odpovídáte?“ — jen se skutečnou dobou odezvy.

## Rozsah a rizika

- Jen `SupportPage.php` + styly v `admin.css` (rozvržení stránky) — žádná nová komponenta, použijí se `.wpify-card`, `.wpify-badge`, `.wpify-notice`.
- Filtry `wpify_dashboard_support_*` zůstávají (FAQ, karty, diagnostika, přílohy) se stejnými argumenty.
- Odesílání (`handle_support_request`) se nemění, kromě čtení checkboxů logů — název pole `log_files[]` zůstane stejný.
- Nové řetězce → překlady `wpify-core` (cs) je potřeba doplnit.
