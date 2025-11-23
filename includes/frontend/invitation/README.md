# Invitation System

Standalone online pre-registration system for visitors.

## URL Structure

- `/visitor-invitation/{token}/` → Direct render (no redirect)
- Query params: `?step={step_name}`

## Flow Steps

1. `language` - Language selection
2. `risks` - Risk upload (optional)
3. `visitors` - Visitor registration
4. `training-*` - Training modules (optional)
5. `success` - PIN display

## Database

- `saw_visits` - status: 'pending' → 'draft' → 'confirmed'
- `saw_visitors` - participation_status: 'planned' → 'confirmed'
- `saw_visit_invitation_materials` - uploaded risks

## Session

- Uses `invitation_flow` session
- Auto-cleanup after 60s on success page

## NEVER DOES

- ❌ Check-in (no daily_logs with checked_in_at)
- ❌ Check-out
- ❌ Uses terminal session
- ❌ Requires authentication

## Communication with Terminal

Only through database:

- Invitation creates/updates: visits, visitors
- Terminal does check-in: creates daily_logs

## Nonce Strategy

- `saw_invitation_step` - For all invitation step forms
- `saw_invitation_autosave` - For autosave AJAX calls
- `saw_clear_invitation_session` - For session cleanup

## Cache Strategy

- Invitation cache group: `invitations`
- Cache keys: `invitation_visit_{visit_id}`
- TTL: 300s (5min) for visits, 600s (10min) for training content
- Auto-invalidation on visit update

