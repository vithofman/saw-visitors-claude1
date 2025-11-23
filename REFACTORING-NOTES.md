# Současné chování před refactoringem

## URL Flow:

- Invitation: `/visitor-training/{token}/` → redirect `/terminal/?invitation=1`
- Terminal detekuje `?invitation=1`
- Nastaví `$flow['mode'] = 'invitation'` v `terminal_flow` session

## Session:

- `invitation_flow` v `$_SESSION` (nastaveno v routeru)
- `terminal_flow` kontaminován s `mode=invitation` (přepisuje invitation_flow)

## Problémy:

- Terminal má invitation-specific kód všude
- `if ($flow['mode'] === 'invitation')` checks v 15+ místech
- Sdílený registrační formulář pro 3 režimy (planned, walk-in, invitation)
- Zamotaná routing logika
- Invitation router redirectuje na terminal místo přímého renderu

## Současná struktura:

```
includes/frontend/
├── invitation/
│   ├── invitation-router.php      # Redirectuje na /terminal/?invitation=1
│   └── ajax-handlers.php
├── terminal/
│   ├── terminal.php                # Obsahuje handle_invitation_mode()
│   ├── steps/
│   │   └── invitation/
│   │       ├── risks-upload.php
│   │       └── pin-success.php
│   └── ...
└── terminal-route-handler.php      # Načítá terminal.php
```

## Datum refactoringu:

Začátek: 2024-12-XX
Plánovaný konec: 2024-12-XX

