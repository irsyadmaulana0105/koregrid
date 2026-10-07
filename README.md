# KoreGrid

Enterprise B2B Supply Chain & Multi-Warehouse Portal.

Proyek portofolio yang menunjukkan Software Engineering, DevOps, dan System Security dalam satu domain B2B.

## Fitur Utama

1. **Multi-Tier Pricing & MOQ**: harga dinamis per tier perusahaan (Gold, Silver, Bronze) dan syarat Minimum Order Quantity.
2. **Real-time Stock Locking**: stok gudang terkunci otomatis saat draft PO dibuat, mencegah overselling.
3. **End-to-End Approval Workflow**: Draft PO → Finance Approval → Warehouse Packing → Shipped → Completed + Invoice.
4. **Granular RBAC & Audit Trail**: Super Admin, Finance, Warehouse Staff, B2B Buyer, dengan log perubahan data sensitif.

## Tech Stack

| Area | Teknologi |
|---|---|
| Aplikasi | Laravel 11/12, Vue 3, Inertia.js, Tailwind CSS |
| Data | PostgreSQL 16, Redis 7 |
| Infrastruktur | Docker Compose (App, Postgres, Redis, Nginx), GitHub Actions |
| Keamanan | RBAC (Spatie), Audit Trail, Rate Limiting, Input Sanitization |

## Dokumentasi

Lihat [`docs/`](docs/README.md).

## Quick Start

Akan dilengkapi setelah source Laravel ditambahkan (Tahap 2).

## Status

- [x] Tahap 1: Inisiasi & arsitektur dokumentasi
- [ ] Tahap 2: Laravel, Migrations, Seeders
