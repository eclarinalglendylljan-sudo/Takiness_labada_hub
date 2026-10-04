# Lab 3 test results: Issue #2 (max_quantity enforcement)
Tester: Glendyll Jan Eclarinal | Date: 2026-10-03 | Branch: feature/2-inventory-max-quantity-validation
Environment: local XAMPP (Apache + MySQL), Owner account, throwaway items (TEST-ITEM, Test-Zero in the first run; TEST-ITEM, TEST-GONE, TEST-GONE2, TEST-GONE3 in the re-run).

## Run 1: original implementation (commit c616870)

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

## Run 2: re-run after the corrective commit (review feedback)

Changes under test: the cap check now happens inside the UPDATE statement for both restock (`quantity + ? <= max_quantity`) and edit (`quantity <= ?`), and the item lookup is shared in one helper, `find_inventory_item_or_redirect()`.

| # | Case | Action | Actual | Result |
|---|------|--------|--------|--------|
| R1 | Restock above max (already at max) | TEST-ITEM 80/80, restock +1 | Red "Cannot restock "TEST-ITEM": 80 + 1 = 81 would exceed the maximum of 80."; qty stayed 80; no new row in the Recent Restocks panel | Pass |
| R2 | New max below quantity | TEST-ITEM qty 80, edit max to 10 | Red "Max quantity (10) cannot be lower than the current quantity (80)."; reopening the edit box showed max still 80 | Pass |
| R3 | New max equals quantity, nothing else changed | TEST-ITEM 80/80, Save with no changes | Green "TEST-ITEM updated." (no false error) | Pass |
| R4 | Valid edit, then restock exactly at max | Edit max to 100, then restock +20 | Green "updated", then green "Stock updated successfully"; qty 100/100; Recent Restocks shows TEST-ITEM (+20) | Pass |
| R5 | Rejected edit changes nothing | TEST-ITEM qty 100: rename to TEST-RENAME and max to 10 | Red "Max quantity (10) cannot be lower than the current quantity (100)."; DB SELECT shows TEST-ITEM, qty 100, max 100 | Pass |
| R6 | Edit an item deleted in another tab | Open edit on TEST-GONE2, delete it in phpMyAdmin, then Save | Red "Item not found." | Pass |
| R7 | Restock an item deleted in another tab | Fill restock for TEST-GONE3 (+10), delete it in phpMyAdmin, then submit | Red "Item not found."; no new restock row | Pass |

## Limitations
- Manual tests on a local database; no automated tests.
- The cap check is now part of the UPDATE statement, so the database itself refuses a change that would break the cap. This was not tested with truly simultaneous requests; it was tested one request at a time.
- The restock UPDATE and the restock log INSERT are separate statements (not wrapped in a transaction).
- "No new restock row" was checked using the Recent Restocks panel on the page, not by counting rows in the inventory_logs table.
- The app has no way to deduct stock, so quantity can only increase through restock.