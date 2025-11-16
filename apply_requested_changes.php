<?php
require_once 'config/db_connect.php';

function logMsg($msg) { echo $msg . "\n"; }

try {
    // 1) Remove supplier Pepsi Co.
    $deletePepsi = $conn->prepare("DELETE FROM supplier WHERE CompanyName = ?");
    $namePepsi = 'Pepsi Co.';
    $deletePepsi->bind_param('s', $namePepsi);
    $deletePepsi->execute();
    logMsg("Removed supplier 'Pepsi Co.' (affected: " . $deletePepsi->affected_rows . ")");

    // 2) Split 'Coca Cola, San Miguel' into 'Coke' and 'San Miguel Beer'
    $checkCombo = $conn->prepare("SELECT SupplierID, CompanyName, ContactNumber FROM supplier WHERE CompanyName = ? LIMIT 1");
    $comboName = 'Coca Cola, San Miguel';
    $checkCombo->bind_param('s', $comboName);
    $checkCombo->execute();
    $comboResult = $checkCombo->get_result();

    if ($comboResult && $comboResult->num_rows === 1) {
        $row = $comboResult->fetch_assoc();
        $supplierId = $row['SupplierID'];
        $contact = $row['ContactNumber'];

        // Update existing record to 'Coke'
        $updateCoke = $conn->prepare("UPDATE supplier SET CompanyName = ? WHERE SupplierID = ?");
        $newNameCoke = 'Coke';
        $updateCoke->bind_param('ss', $newNameCoke, $supplierId);
        $updateCoke->execute();
        logMsg("Updated supplier '$comboName' -> 'Coke' (affected: " . $updateCoke->affected_rows . ")");

        // Insert new record for 'San Miguel Beer'
        $newSupplierId = 'Sup-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $newNameSMB = 'San Miguel Beer';
        $newContact = '9052562399';
        $insertSMB = $conn->prepare("INSERT INTO supplier (SupplierID, CompanyName, ContactNumber) VALUES (?, ?, ?)");
        $insertSMB->bind_param('sss', $newSupplierId, $newNameSMB, $newContact);
        $insertSMB->execute();
        logMsg("Inserted supplier 'San Miguel Beer' as $newSupplierId");
    } else {
        // Ensure 'Coke' exists even if combo row missing
        $ensureCoke = $conn->prepare("SELECT SupplierID FROM supplier WHERE CompanyName = 'Coke' LIMIT 1");
        $ensureCoke->execute();
        $r = $ensureCoke->get_result();
        if ($r->num_rows === 0) {
            $newId = 'Sup-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            $contact = '9052562376';
            $ins = $conn->prepare("INSERT INTO supplier (SupplierID, CompanyName, ContactNumber) VALUES (?, 'Coke', ?)");
            $ins->bind_param('ss', $newId, $contact);
            $ins->execute();
            logMsg("Inserted supplier 'Coke' as $newId");
        }
        // Ensure 'San Miguel Beer' exists
        $ensureSMB = $conn->prepare("SELECT SupplierID FROM supplier WHERE CompanyName = 'San Miguel Beer' LIMIT 1");
        $ensureSMB->execute();
        $r2 = $ensureSMB->get_result();
        if ($r2->num_rows === 0) {
            $newId2 = 'Sup-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            $contact2 = '9052562399';
            $ins2 = $conn->prepare("INSERT INTO supplier (SupplierID, CompanyName, ContactNumber) VALUES (?, 'San Miguel Beer', ?)");
            $ins2->bind_param('ss', $newId2, $contact2);
            $ins2->execute();
            logMsg("Inserted supplier 'San Miguel Beer' as $newId2");
        }
    }

    // 3) Rename products from 'Coca-Cola' to 'Coke' (and 'Coca Cola' to 'Coke')
    $updateProducts1 = $conn->query("UPDATE product SET ProductName = REPLACE(ProductName, 'Coca-Cola', 'Coke')");
    logMsg("Renamed products 'Coca-Cola' -> 'Coke' (affected: " . $conn->affected_rows . ")");
    $updateProducts2 = $conn->query("UPDATE product SET ProductName = REPLACE(ProductName, 'Coca Cola', 'Coke')");
    logMsg("Renamed products 'Coca Cola' -> 'Coke' (affected: " . $conn->affected_rows . ")");

    // 4) Delete customer with ID 4 (best-effort based on current schema)
    $deletedSpecific = 0;
    $delSpecific = $conn->prepare("DELETE FROM customer WHERE CustomerID = ?");
    $id4 = '4';
    $delSpecific->bind_param('s', $id4);
    $delSpecific->execute();
    $deletedSpecific += $delSpecific->affected_rows;

    // If not found, try 'C-00004'
    $id4b = 'C-00004';
    $delSpecific2 = $conn->prepare("DELETE FROM customer WHERE CustomerID = ?");
    $delSpecific2->bind_param('s', $id4b);
    $delSpecific2->execute();
    $deletedSpecific += $delSpecific2->affected_rows;

    if ($deletedSpecific === 0) {
        // As a fallback, delete the 4th customer by alphabetical CustomerID order
        $res = $conn->query("SELECT CustomerID, CustomerName FROM customer ORDER BY CustomerID ASC LIMIT 1 OFFSET 3");
        if ($res && $res->num_rows === 1) {
            $c = $res->fetch_assoc();
            $cid = $c['CustomerID'];
            $del = $conn->prepare("DELETE FROM customer WHERE CustomerID = ?");
            $del->bind_param('s', $cid);
            $del->execute();
            logMsg("Deleted 4th customer: " . $cid . " (" . $c['CustomerName'] . ")");
        } else {
            logMsg("No 4th customer found to delete.");
        }
    } else {
        logMsg("Deleted customer(s) with ID '4' or 'C-00004' (affected: $deletedSpecific)");
    }

    logMsg("All requested changes applied.");
} catch (Throwable $e) {
    logMsg("Error applying changes: " . $e->getMessage());
}
