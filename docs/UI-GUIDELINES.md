# UI Guidelines

The terminal must be kjempepent: beautiful, premium, calm, fast, mobile-first, and app-like.

It must not feel like WordPress admin.

## Design Principles

- Norwegian UI text.
- Large touch targets.
- Soft rounded cards.
- Clean typography.
- Clear status badges.
- Calm colors.
- High contrast.
- Minimal clutter.
- Excellent on iPhone and iPad.
- Fast perceived performance.
- Clear action hierarchy.

## Tone

Use practical Norwegian staff language:

- `Logg inn`
- `Bytt profil`
- `Logg ut`
- `Start plukking`
- `Marker klar`
- `Bekreft betalt i butikk`
- `Marker hentet`
- `Marker problem`
- `Tilbake`

## Screen 1: Login/Profile

```text
Lilleprinsen
Klikk og hent

[Velg ansattprofil]
[PIN]

[Logg inn]
```

Requirements:

- Clear profile selection.
- Numeric PIN input.
- Generic failed login message.
- Touch-friendly layout.

## Screen 2: Locked

```text
Terminal låst
Innlogget som {name}

[PIN]
[Fortsett]
[Bytt profil]
```

Requirements:

- No order data visible while locked.
- PIN unlock for current profile.
- Option to switch profile.

## Screen 3: Order Overview

```text
[Lilleprinsen]              [{profile}] [Bytt profil] [Logg ut]

[Søk etter hentenummer, ordre, navn, telefon eller e-post]

[Nye] [Plukkes] [Klar] [Hentet] [Problem]
[Alle] [Betalt på nett] [Må betales i butikk]

┌──────────────────────────────┐
│ H1001              Klar      │
│ Kari Nordmann                │
│ Ordre #1234 · 3 varer        │
│ Betalt på nett      [Åpne]   │
└──────────────────────────────┘
```

Order cards show:

- large hentenummer
- customer name
- order number
- item count
- payment badge
- status badge
- `Åpne` button

## Screen 4: Order Detail

```text
[Tilbake]                       H1001

Kari Nordmann
Ordre #1234
Telefon
E-post

Status
Intern status: Klar
WooCommerce: Klar for henting

Betaling
Må betales i butikk
Beløp: 799,-
[Bekreft betalt i butikk]

Varer
- Produktnavn, antall, SKU

Intern note
[tekstfelt]

Historikk
- 10:30 Anna markerte ordren som klar

[Start plukking] [Marker klar]
[Marker hentet] [Marker problem]
```

## Payment Badges

- `Betalt på nett`
- `Må betales i butikk`
- `Betaling må sjekkes`

Pay-in-store state must be visually clear and hard to miss.

## Status Tabs

- `Nye`
- `Plukkes`
- `Klar`
- `Hentet`
- `Problem`

Tabs represent internal pickup states, not raw WooCommerce statuses.

## Accessibility

- High contrast text.
- Touch targets at least 44px.
- Do not rely on color alone.
- Visible focus states.
- Good keyboard behavior for PIN/search.
