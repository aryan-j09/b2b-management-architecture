# End-to-End B2B Management System (Architecture Showcase)

> **Note:** Due to strict NDAs, the full source code for this system is proprietary and cannot be published. This repository serves as a public-safe architectural summary, containing sanitized database schemas, API flows, and core backend logic to demonstrate system design, relational data management, and server-side execution. All data and identifiers used below are dummy placeholders.

## System Overview
This system is a comprehensive, multi-module B2B manufacturing and financial management platform engineered to securely track high-volume operations (tracking over ₹1.1Cr+ in active financial flow). 

The backend relies on a pragmatic PHP MVC-style architecture. `classes/Master.php` acts as a centralized action dispatcher for asynchronous JavaScript requests. For data integrity, the server pushes consistency checks into the backend: purchase orders dynamically recompute totals and payment splits, receiving validates that quantities do not exceed ordered amounts, and utilization writes to a ledger before marking stock as consumed. Write paths utilize prepared statements, strict transaction boundaries, and server-side session validation.

---

## 1. Core Database Schema Design

The system relies on a highly relational MySQL architecture. Below are the sanitized schemas for three interconnected core modules.

### Module A: Procurement & Orders
Manages suppliers, item catalogs, and purchase orders with strict foreign key constraints.
```
CREATE TABLE supplier_list (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  gst_number VARCHAR(15) DEFAULT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_order_list (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  po_code VARCHAR(50) NOT NULL UNIQUE,
  supplier_id INT UNSIGNED NOT NULL,
  grand_total DECIMAL(10,2) DEFAULT 0.00,
  paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  close_status ENUM('open','closed') NOT NULL DEFAULT 'open',
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES supplier_list(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Module B: Client Fulfillment & Payment Scheduling
Tracks client billing, proforma invoices, and multi-stage payment schedules (advance, inspection, installation, credit).
```
CREATE TABLE proforma_invoice_list (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  po_code VARCHAR(50) NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  advance_payment_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  remaining_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Module C: SKU Tracking & Inventory Ledger
Tracks individual unit movements, linking specific stock to purchase orders and usage history.

```
CREATE TABLE item_tracking_tags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tracking_code VARCHAR(255) NOT NULL UNIQUE,
  item_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  status ENUM('ACTIVE','FULLY_USED','CANCELLED') DEFAULT 'ACTIVE',
  CONSTRAINT fk_tracking_item FOREIGN KEY (item_id) REFERENCES item_list(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_movement (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  movement_type ENUM('IN','OUT') NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  tracking_id INT UNSIGNED DEFAULT NULL,
  balance_after INT DEFAULT NULL,
  CONSTRAINT fk_stock_item FOREIGN KEY (item_id) REFERENCES item_list(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```


## 2. Asynchronous API Flow (REST-style Handlers)
The frontend communicates with the server via optimized asynchronous payloads. Below are examples of critical state-mutating endpoints.

### A. Update PO Paid Amount

Method: POST /classes/Master.php?f=update_po_paid_amount

Payload: {"id": 102, "paid_amount": 250000.00}

Response: {"status": "success", "msg": "Paid amount updated successfully."}

### B. Save Received Inventory Asset

Method: POST /classes/Master.php?f=save_received_inventory

Payload: {"tracking_code": "PO-102-IT-236-ABC123", "item_id": 236, "quantity": 10}

Response: {"status": "success", "tracking_id": 501}

### C. Get Aggregated PO Summary

Method: POST /classes/Master.php?f=get_po_summary

Payload: {"start_date": "2026-01-01", "end_date": "2026-06-30"}

Response: {"overall_total": 1845000.00, "by_company": [...]}


## 3. Complex Logic Snippet: Order State Reconciliation
The following abstracted PHP method demonstrates how the system securely reconciles purchase order states. It cross-references total payments against total ordered and received stock quantities using prepared statements to prevent injection and enforce strict business logic before closing an order.

(See src/OrderReconciliationService.php for the full abstracted logic).
