-- Membuktikan constraint penting di database benar-benar menolak data yang salah.
-- Aman dijalankan kapan saja: semua data uji di-ROLLBACK di akhir.
--
-- Jalankan:
--   docker compose exec -T postgres sh -c 'psql -q -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < database/sql/verify-constraints.sql
\set QUIET on
BEGIN;

CREATE TEMP TABLE t_results (n serial, ok boolean, label text);

-- Pernyataan harus GAGAL dengan SQLSTATE tertentu.
CREATE FUNCTION pg_temp.expect_error(label text, stmt text, expected_state text) RETURNS void
LANGUAGE plpgsql AS $$
BEGIN
    BEGIN
        EXECUTE stmt;
        INSERT INTO pg_temp.t_results (ok, label) VALUES (false, label || ' (tidak ditolak)');
    EXCEPTION WHEN OTHERS THEN
        IF SQLSTATE = expected_state THEN
            INSERT INTO pg_temp.t_results (ok, label) VALUES (true, label);
        ELSE
            INSERT INTO pg_temp.t_results (ok, label)
            VALUES (false, label || ' (error ' || SQLSTATE || ', diharapkan ' || expected_state || ')');
        END IF;
    END;
END;
$$;

-- Pernyataan harus mengubah tepat N baris.
CREATE FUNCTION pg_temp.expect_rows(label text, stmt text, expected bigint) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE n bigint;
BEGIN
    EXECUTE stmt;
    GET DIAGNOSTICS n = ROW_COUNT;
    INSERT INTO pg_temp.t_results (ok, label)
    VALUES (n = expected, label || CASE WHEN n = expected THEN '' ELSE ' (baris: ' || n || ', diharapkan ' || expected || ')' END);
END;
$$;

\o /dev/null
-- Data uji
INSERT INTO companies (code, name, created_at, updated_at) VALUES ('T-CMP', 'Test Co', now(), now()) RETURNING id AS company_id \gset
INSERT INTO warehouses (code, name, created_at, updated_at) VALUES ('T-WH', 'Test WH', now(), now()) RETURNING id AS wh_id \gset
INSERT INTO products (sku, name, unit, created_at, updated_at) VALUES ('T-SKU', 'Test Product', 'pcs', now(), now()) RETURNING id AS product_id \gset
INSERT INTO users (company_id, name, email, password, created_at, updated_at) VALUES (:company_id, 'Tester', 'Tester@Example.com', 'x', now(), now()) RETURNING id AS user_id \gset
INSERT INTO product_tiers (product_id, tier, unit_price, moq, created_at, updated_at) VALUES (:product_id, 'gold', 100000, 5, now(), now());
INSERT INTO inventories (product_id, warehouse_id, qty_on_hand, qty_reserved, created_at, updated_at) VALUES (:product_id, :wh_id, 10, 0, now(), now());
INSERT INTO purchase_orders (po_number, company_id, buyer_id, pricing_tier, created_at, updated_at) VALUES ('T-PO-1', :company_id, :user_id, 'gold', now(), now()) RETURNING id AS po_id \gset

-- Enum dan nilai
SELECT pg_temp.expect_error('companies: tier tidak valid ditolak',
  $$INSERT INTO companies (code, name, pricing_tier, created_at, updated_at) VALUES ('X1', 'X', 'platinum', now(), now())$$, '23514');
SELECT pg_temp.expect_error('product_tiers: tier tidak valid ditolak',
  format($$INSERT INTO product_tiers (product_id, tier, unit_price, moq, created_at, updated_at) VALUES (%s, 'platinum', 1, 1, now(), now())$$, :product_id), '23514');
SELECT pg_temp.expect_error('product_tiers: harga negatif ditolak',
  format($$INSERT INTO product_tiers (product_id, tier, unit_price, moq, created_at, updated_at) VALUES (%s, 'silver', -1, 1, now(), now())$$, :product_id), '23514');
SELECT pg_temp.expect_error('product_tiers: MOQ 0 ditolak',
  format($$INSERT INTO product_tiers (product_id, tier, unit_price, moq, created_at, updated_at) VALUES (%s, 'silver', 1, 0, now(), now())$$, :product_id), '23514');
SELECT pg_temp.expect_error('product_tiers: tier ganda per produk ditolak',
  format($$INSERT INTO product_tiers (product_id, tier, unit_price, moq, created_at, updated_at) VALUES (%s, 'gold', 1, 1, now(), now())$$, :product_id), '23505');

-- Integritas stok
SELECT pg_temp.expect_error('inventories: reserved > on_hand ditolak',
  format($$UPDATE inventories SET qty_reserved = 11 WHERE product_id = %s$$, :product_id), '23514');
SELECT pg_temp.expect_error('inventories: stok fisik negatif ditolak',
  format($$UPDATE inventories SET qty_on_hand = -1 WHERE product_id = %s$$, :product_id), '23514');

-- Reservasi atomik (stok 10): 4 OK, 7 gagal (sisa 6), 6 OK, 1 gagal (habis)
SELECT pg_temp.expect_rows('reserve 4 dari stok 10 berhasil',
  format($$UPDATE inventories SET qty_reserved = qty_reserved + 4 WHERE product_id = %s AND warehouse_id = %s AND qty_on_hand - qty_reserved >= 4$$, :product_id, :wh_id), 1);
SELECT pg_temp.expect_rows('reserve 7 dari sisa 6 menghasilkan 0 baris',
  format($$UPDATE inventories SET qty_reserved = qty_reserved + 7 WHERE product_id = %s AND warehouse_id = %s AND qty_on_hand - qty_reserved >= 7$$, :product_id, :wh_id), 0);
SELECT pg_temp.expect_rows('reserve 6 dari sisa 6 berhasil',
  format($$UPDATE inventories SET qty_reserved = qty_reserved + 6 WHERE product_id = %s AND warehouse_id = %s AND qty_on_hand - qty_reserved >= 6$$, :product_id, :wh_id), 1);
SELECT pg_temp.expect_rows('reserve 1 saat stok habis menghasilkan 0 baris',
  format($$UPDATE inventories SET qty_reserved = qty_reserved + 1 WHERE product_id = %s AND warehouse_id = %s AND qty_on_hand - qty_reserved >= 1$$, :product_id, :wh_id), 0);

-- Purchase order
SELECT pg_temp.expect_error('purchase_orders: status tidak valid ditolak',
  format($$UPDATE purchase_orders SET status = 'unknown' WHERE id = %s$$, :po_id), '23514');
SELECT pg_temp.expect_error('purchase_orders: total negatif ditolak',
  format($$UPDATE purchase_orders SET total_amount = -1 WHERE id = %s$$, :po_id), '23514');
SELECT pg_temp.expect_error('purchase_orders: po_number ganda ditolak',
  format($$INSERT INTO purchase_orders (po_number, company_id, buyer_id, pricing_tier, created_at, updated_at) VALUES ('T-PO-1', %s, %s, 'gold', now(), now())$$, :company_id, :user_id), '23505');
SELECT pg_temp.expect_error('purchase_order_items: quantity 0 ditolak',
  format($$INSERT INTO purchase_order_items (purchase_order_id, product_id, warehouse_id, quantity, unit_price, moq_applied, line_total, created_at, updated_at) VALUES (%s, %s, %s, 0, 1, 1, 0, now(), now())$$, :po_id, :product_id, :wh_id), '23514');
INSERT INTO purchase_order_items (purchase_order_id, product_id, warehouse_id, quantity, unit_price, moq_applied, line_total, created_at, updated_at) VALUES (:po_id, :product_id, :wh_id, 5, 100000, 5, 500000, now(), now());
SELECT pg_temp.expect_error('purchase_order_items: item ganda (PO, produk, gudang) ditolak',
  format($$INSERT INTO purchase_order_items (purchase_order_id, product_id, warehouse_id, quantity, unit_price, moq_applied, line_total, created_at, updated_at) VALUES (%s, %s, %s, 5, 1, 1, 5, now(), now())$$, :po_id, :product_id, :wh_id), '23505');

-- Users dan relasi
SELECT pg_temp.expect_error('users: email sama beda huruf besar/kecil ditolak',
  format($$INSERT INTO users (company_id, name, email, password, created_at, updated_at) VALUES (%s, 'Dup', 'tester@example.com', 'x', now(), now())$$, :company_id), '23505');
SELECT pg_temp.expect_error('companies: hapus company yang masih punya user ditolak',
  format($$DELETE FROM companies WHERE id = %s$$, :company_id), '23503');

-- Audit log append-only
SELECT pg_temp.expect_rows('audit_logs: INSERT diizinkan',
  format($$INSERT INTO audit_logs (user_id, event, auditable_type, auditable_id) VALUES (%s, 'created', 'App\Models\Test', 1)$$, :user_id), 1);
SELECT pg_temp.expect_error('audit_logs: UPDATE ditolak',   $$UPDATE audit_logs SET event = 'x'$$, '23001');
SELECT pg_temp.expect_error('audit_logs: DELETE ditolak',   $$DELETE FROM audit_logs$$, '23001');
SELECT pg_temp.expect_error('audit_logs: TRUNCATE ditolak', $$TRUNCATE audit_logs$$, '23001');

-- Hasil
\o
\set QUIET off
SELECT CASE WHEN ok THEN 'PASS' ELSE 'FAIL' END AS hasil, label FROM t_results ORDER BY n;
SELECT count(*) FILTER (WHERE ok) AS lulus, count(*) FILTER (WHERE NOT ok) AS gagal FROM t_results;

ROLLBACK;
