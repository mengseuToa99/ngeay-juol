# TODO

- Add a dedicated room/rental charge-rule overview so inherited property rules and overrides are visible outside billing review.
- Add focused Filament tests for property-wide, room-specific, and rental-specific applicability actions.
- Add a browser-level regression for the monthly and utility billing review screens showing `Free`, `Waived`, `Not applicable`, and `Skip this cycle`.
- Revisit the legacy utility waiver surface and remove it if charge rules are now the only intended landlord workflow.
- Re-run the feature suite on a machine with SQLite PDO enabled to validate the new override path end to end.
