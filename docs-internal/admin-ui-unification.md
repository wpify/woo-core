# Sjednocení admin UI do woo-core

Stav k 2026-10-02. Podklad: inventura admin stylů ve všech WPify pluginech, rozbor editoru discount rules a feed-manageru. Ověřováno v prohlížeči na ddev (new-plugins.test).

## Výchozí stav

- **woo-core `assets/admin.css`** (~1020 ř.) obsahuje: layout WPify stránek, kartu `.wpify__card` (dashboard), badge `.wpify-badge-*`, notice `.wpify-notice-*`, toggle (WCF + `.wpify__card .toggle-button`), tabulky (`wp-list-table` + WCF), grid post editoru se sticky sidebarem a taby v metaboxu.
- **Načítá se** jen na obrazovkách s lištou WPify (`MenuBar::should_render()`), záměrně až v `<body>`, aby přebil styly WPify Custom Fields vkládané za běhu.
- **Pluginy s vlastním admin CSS:** discount-rules (~580 ř. inline v PHP), feed-manager (~1440 ř. SCSS), sales-booster-kit (202 ř.), fakturoid (69 ř.), comgate/gopay/thepay (stavové badge, 3× kopie), drobnosti v ostatních.
- **Bez vlastního admin CSS:** benefity-cz, ceska-posta, custom-feeds, dognet, feeds, gls, gpwebpay, order-status-manager, ppl, slovak-post, smartform, sodexo, vivnetworks-affiliate, zbozi-conversions, phone-validation, seznam-login, raynet, conditional-shipping, mapy-cz.

## Opakující se vzory (kandidáti do core)

| Vzor | Dnes implementováno | Návrh v core |
|---|---|---|
| Karta s hlavičkou a tělem | DR `.components-card` (DiscountRulePostType.php ~255), FM wizard karty (wizard.scss 116–212), SBK karta (style.scss 53–71), wpify-woo newsletter (Settings.php 200–245) | Stylovat WP `Card/CardHeader/CardBody` uvnitř `.wpify-admin-page` + třídová varianta `.wpify-card` pro PHP markup |
| Sekce s tónem (default/success/danger/warning) | DR `.wpify-rule-section` (~300–335), FM konfliktní řádky (wizard.scss 215–273), wpify-woo IcDic box (inline) | `.wpify-section` + `--success/--danger/--warning/--info`, `__head`, `__content` |
| Číslovaný repeater se sidebarem | DR 3× kopie: filter/condition/range (~350–410), core `.wpify_group_container--row` | `.wpify-repeater__item/__sidebar/__content/__actions` |
| Logický konektor (OR) a operátor (AND) | DR (~385–440) | `.wpify-connector`, `.wpify-operator` |
| Accordion | DR `.wpify-rules-accordion` (~440–475) | `.wpify-accordion` |
| Tooltip | DR 2× (inline editor + admin_head) | `[data-wpify-tooltip]` (čisté CSS) |
| Modal | DR vlastní vanilla modal, FM a DR WP `<Modal>` s overridy | Styl WP `Modal` v core; vanilla modal jen pokud je potřeba bez Reactu |
| Stavový badge | comgate/gopay/thepay (inline barvy, 3× copy-paste), fakturoid `.invoice-status`, DR `.wpify-rule-summary__status`, DR AdminProduct (předefinuje `.wpify-badge`!), FM chipy | Jen core `.wpify-badge-*` |
| Toggle | core (2 varianty), FM `.wpify-fm-toggle`, SBK `.wpify-sb-toggle`, DR zmenšený | Jedna velikost + kompaktní modifikátor v core |
| Icon button, spinner, empty state, status ikona | FM (3 různé spinnery), DR | `.wpify-icon-btn`, `.wpify-spinner`, `.wpify-empty`, `.wpify-status-icon` |
| Notice / text chyby | `#b32d2e`/`red` inline ve fakturoid, FM, heureka, phone-validation; `wpify-notice-info` použité, ale v core chybí | doplnit `.wpify-notice-info`, utilita `.wpify-text-error` |
| `<code>` ke zkopírování | comgate, thepay, fakturoid, heureka, sodexo (inline `user-select:all`) | `.wpify-code-copy` |
| Červený pruh „update“ v seznamu pluginů | wpify-woo, fakturoid, cross-sell (stejný kód 3×) | patří do `AbstractPlugin` (PHP), ne CSS |

## Tokeny

- `--wpify-core-success/danger/warning` jsou klíčová slova `green/red/orange`, proto si pluginy berou vlastní odstíny (Tailwind, WP admin paleta). Nahradit konkrétními hodnotami a přidat měkké varianty přes `color-mix` (jak už to dělá badge).
- DR `--dr-border` a `--dr-bg-soft` jsou doslova kopie `--wpify-core-light` a `--wpify-core-bg-soft`.
- Feed-manager nepoužívá `--wpify-core-primary` vůbec (WP modrá `#2271b1`); term-matrix má dva focus systémy (WP modrá vs. core fialová `--wpify-core-secondary`).
- Chybí token pro „info/logický konektor“ (DR indigo) — buď nový `--wpify-core-info`, nebo `--wpify-core-secondary`.

## Zjištěné vady a rizika

1. ~~custom-feeds~~ — nevydaný předchůdce feed-manageru, ignorovat.
2. **sales-booster-kit** přepisuje `$_GET['page']` na `wpify/…` (`src/Admin/Settings.php:241`), takže se na jeho stránkách načte core lišta i `admin.css`, ale bez body třídy `wpify-admin-page`. Dnešní změny core CSS ho tak mohou ovlivnit — ověřit na sestaveném SBK (lokálně jsou složky modulů prázdné).
3. **fakturoid** `admin.scss` se načítá na všech admin stránkách; **comgate/gopay/thepay** tisknou styly v `admin_head` bez kontroly obrazovky. Je to záměr — stylují prvky mimo stránky pluginu (výpis a detail objednávky). Řeší to rozdělení core CSS níže.

## Dva soubory v core

| | `components.css` (nový) | `admin.css` (stávající) |
|---|---|---|
| Obsah | tokeny `:root`, badge, stavové prvky, notice, tooltip, `code` ke zkopírování, text chyby, spinner — jen prvky s vlastní třídou `wpify-*` | layout WPify stránek, lišta, karty a sekce nastavení, WCF overridy, tabulky, post editor |
| Kde se načítá | kdekoli v adminu, kde ho plugin potřebuje (výpis/detail objednávky, produkt…) + automaticky na WPify stránkách | jen na obrazovkách s lištou WPify (`MenuBar::should_render()`) |
| Pravidla | žádné selektory na WP/WCF prvky ani na `body`/`#wpbody` — nesmí nic změnit mimo vlastní třídy | může přepisovat WP i WCF |
| Pořadí | v `<head>` (nic nepřepisuje, pořadí nevadí) | v `<body>` (musí být za WCF) |
| Handle | `wpify-core-components`, registruje zvolená kopie core | `wpify-core-admin`, závislost na `wpify-core-components` |

- Plugin, který stylí objednávky, zavolá na svých obrazovkách `wp_enqueue_style( 'wpify-core-components' )` místo vlastního CSS (fakturoid faktura-badge, platební brány stav platby).
- Riziko: na webu může vyhrát starší kopie core, která handle nezaregistruje — `wp_enqueue_style` neregistrovaného handle tiše nic nenačte. Dokud nebudou všechny pluginy na nové verzi core, potřebují pluginy fallback (např. pomocná metoda v core, která handle zaregistruje ze své kopie, pokud chybí).
4. **Mrtvé CSS:** wpify-woo `assets/admin/settings.scss` + `LicenseControl.scss` (enqueue zakomentovaný, `src/Admin/Settings.php:89`), wpify-ads `.js-wcf` (selektor z WCF v3).
5. **DR** `.components-panel__body*` pravidla jsou mrtvá (`PanelBody` se nepoužívá), `--dr-border-soft` nepoužité, tooltip definovaný dvakrát.
6. **term-matrix** bojuje s plošným pravidlem core pro inputy (`#wpbody` prefixy, reset checkboxů). Plošné pravidlo má zůstat (rozhodnutí 2026-10-02) — potřebuje jen opt-out třídu (např. `:not(.wpify-input-quiet)`).

Opraveno 2026-10-02 v discount rules: zapnutý zmenšený toggle přečníval o 4 px (core `translateX(22px)` na 38px stopě) a pod 851 px absolutně umístěný sidebar překrýval editor.

## Postup

Hotovo 2026-10-02: rozdělení na `components.css` + `admin.css`, tokeny `*-soft` a `info`, `.wpify-notice-info`, opt-out `.wpify-input-quiet`, sloučení `tfoot`. Ověřeno porovnáním vypočítaných stylů na 12 obrazovkách (0 rozdílů) a načtením na výpisu objednávek. Hodnoty success/danger/warning zůstaly `green/red/orange` — změna palety je samostatné rozhodnutí (viditelná změna všech badge a notice).

Hotovo 2026-10-02: komponenty v core (karta, sekce, repeater, konektor/operátor, accordion, tooltip, ikonové tlačítko, spinner, prázdný stav, utility, kompaktní a tónovaný toggle) — přehled markupu v `admin-components.md`. Zatím je nic nepoužívá; ověřeno na ukázkové stránce a 0 rozdílů na 12 obrazovkách.

Hotovo 2026-10-02: editor discount rules převedený na komponenty core (JSX třídy, tooltip v PHP shrnutí a sloupci tabulky). Inline CSS editoru 333 → 66 řádků, zbyla doménová pravidla. Discount rules načítá `components.css` na obrazovkách pravidel sám (i v Sales Booster Kit, ověřeno simulací bez `admin.css`).

Hotovo 2026-10-02: feed-manager na komponentách core, pole WCF beze změny. Wizard: vstupní karty a karty šablon na `.wpify-card`, původ šablony jako `.wpify-badge`, prázdný stav `.wpify-empty`, konfliktní řádky jako `.wpify-section--success/--warning/--danger` se stavem v badge, shrnutí jako `.wpify-section`, barvy stepperu z tokenů. Status box: stav posledního běhu jako badge, chyba `.wpify-text-error`. Zdroje dat: `.wpify-spinner` místo WP spinneru, tlačítko pro nové načtení `.wpify-icon-btn`, ikony stavu z tokenů. Zbývá term-matrix (tokeny a `.wpify-input-quiet` místo `#wpbody` hacků) — samostatný krok, tabulka je laděná podle mockupu.

1. **Základ v core:** tokeny (konkrétní success/danger/warning + měkké varianty, info), `.wpify-notice-info`, opt-out pro plošné pravidlo inputů, sloučení duplicitního pravidla `tfoot` (admin.css 739 vs 807).
2. **Komponenty v core** podle tabulky výše (CSS-first: pluginy vypíší markup s třídami; WP komponenty `Card/Modal/ToggleControl` stylované v rámci `.wpify-admin-page`). Sdílený React balíček až pokud bude potřeba stejná logika, ne jen vzhled.
3. **discount rules:** editor převést na třídy z core, inline CSS zmenšit na doménová pravidla (shrnutí, sloupce tabulky, záložka produktu).
4. **feed-manager:** wizard, FeedStatusWidget, SourceStates, filtrové řádky (řádek Type/Operator/Value jako v DR), term-matrix tokeny a opt-out místo `#wpbody` hacků.
5. **ostatní:** badge plateb (comgate/gopay/thepay) a fakturoid na `.wpify-badge-*`, custom-feeds enqueue, úklid mrtvého CSS, SBK sladit nebo vědomě nechat vlastní design.

Každý krok ověřovat porovnáním vypočítaných stylů před/po na dotčených obrazovkách.

## Více kopií jádra na webu (ověřeno 2026-10-02)

Admin řídí kopie s nejvyšší `pretty_version` v `composer/installed.php` (`Admin\Settings`, porovnání `>`). Při shodě verzí vyhraje kopie, která se načte dřív — o kódu to nic neříká.

- **Nové jádro řídí, ostatní pluginy mají 5.6.1/5.6.2:** 22 WPify obrazovek bez chyb PHP, jedna hlavička, `components.css` + `admin.css` z řídící kopie; mimo WPify (nástěnka, produkty, objednávky, pluginy, WC nastavení) se nenačítá nic. Rozhraní mezi kopiemi (`wpify_installed_plugins`, `wpify_get_sections_*`, `wpify_woo_is_settings_page`, `wpify_woo_setting`) se nezměnilo.
- **Staré jádro řídí, plugin nese nové:** bez chyb. Obrazovky, které se do hlavičky hlásí novými filtry (pravidla DR, feedy FM), jsou bez hlavičky a bez `admin.css`; DR si `components.css` načte z vlastní kopie, FM ne (stav běhu bez stylu badge). Funkčně v pořádku.
- **Podmínka vydání:** nové jádro musí dostat vyšší verzi než jakákoli vydaná (5.6.2 je ve wpify-woo-feeds se starým kódem) — tj. 5.7.0. Pluginy, které spoléhají na nové filtry nebo `components.css` (DR, FM), musí vyžadovat tuto verzi. Pak stav „staré řídí, nové v pluginu“ nenastane.
