# Lab 3 test results: Issue #2 (max_quantity enforcement)
Tester: Glendyll Jan Eclarinal | Date: 2026-10-03 | Branch: feature/2-inventory-max-quantity-validation
Environment: local XAMPP (Apache + MySQL), Owner account, throwaway items TEST-ITEM and Test-Zero.

| # | Case | Action | Actual | Result |
|---|------|--------|--------|--------|
| 1 | Valid restock below max | TEST-ITEM 50/100 restocked +5 | Green "Stock updated successfully"; qty becomes 55 | Pass |
| 2 | Restock exactly at max | TEST-ITEM 50 + 50; Test-Zero 0 + 10 (max 10) | Green "Stock updated successfully"; quantities 100 and 10 | Pass |
| 3 | Restock above max | TEST-ITEM 50 + 51; Test-Zero 0 + 20 (max 10) | Red "Cannot restock ... would exceed the maximum"; quantities stayed 50 and 0; no new restock rows | Pass |
| 4 | New max equals quantity | TEST-ITEM 80/100, edit max to 80 | Green success banner; max and qty are both 80 | Pass |
| 5 | New max below quantity | TEST-ITEM qty 100, edit max to 10 | Red "Max quantity (10) cannot be lower than the current quantity (100)." | Pass |
| 6 | Rejected operation changes nothing | Edit TEST-ITEM to name "RENAMED" and max to 10 | Red error banner; DB SELECT confirms name still "TEST-ITEM" and max unchanged | Pass |
| Z | Max higher than quantity at 0 | Test-Zero qty 0, edit max to 1 | Green "updated" | Pass |
| N | Negative restock (existing rule) | Test-Zero restock -9 | Red "Quantity to add must be at least 1." | Pass |

## Limitations
- Manual tests on a local database; no automated tests.
- Check-then-update is not atomic, so two simultaneous requests could pass the check.
- The app has no way to deduct stock, so quantity can only increase through restock.
