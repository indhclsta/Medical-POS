# 🏥 Medical POS – Sistem Kasir Apotek

**Medical POS** adalah aplikasi **Point of Sale** berbasis web yang dirancang khusus untuk apotek, klinik, dan toko obat.  
Aplikasi ini terintegrasi dengan database untuk melakukan proses **CRUD** (Create, Read, Update, Delete) pada data kasir, produk, member, dan transaksi.  
Mendukung **multiuser** dengan dua role utama: **Super Admin** dan **Kasir**, masing-masing dengan hak akses berbeda.

---

## 👥 Role & Hak Akses

### 🔹 Super Admin
- Hanya dibuat sekali (tidak bisa dihapus atau diedit)
- Melihat & mengedit semua data kasir
- Mengatur kategori produk/obat (Obat Bebas, Obat Bebas Terbatas, Obat Keras, Obat Jamu)
- Mengelola produk & stok
- Mengatur sistem member & poin
- Melihat dan mencetak semua laporan/grafik penjualan

### 🔹 Kasir
- Tidak bisa menghapus akun login
- Hanya bisa mengedit data diri sendiri
- Melakukan transaksi penjualan
- Mengelola keranjang belanja (timer otomatis 5 menit, tidak bisa dihapus, ada checklist)
- Mencetak invoice (WA/PDF)
- Mengirim struk transaksi via WhatsApp jika pembeli adalah member
- Mengelola member (tambah/edit)
- Scan QR untuk transaksi
- Melihat dan mencetak laporan penjualan pribadi

---

## 📋 Fitur Per Role

| Fitur                  | Kasir | Super Admin |
|------------------------|:-----:|:-----------:|
| Login                  |   ✅   |     ✅       |
| Lupa Password          |   ✅   |     ✅       |
| Kasir                  |       |     ✅       |
| Member                 |   ✅   |     ✅       |
| Kategori               |       |     ✅       |
| Produk                 |       |     ✅       |
| Transaksi Scan QR      |   ✅   |             |
| Keranjang              |   ✅   |             |
| Invoice WA/PDF         |   ✅   |             |
| Print Laporan/Grafik   |   ✅   |     ✅       |
| Kirim Struk WA Member  |   ✅   |             |

---

## 🏷️ Kategori Obat

1. 🟢 **Obat Bebas**  
2. 🔵 **Obat Bebas Terbatas**  
3. 🔴 **Obat Keras**  
4. 🟢 **Obat Jamu**  

---

## ✨ Fitur Utama

- 📦 Manajemen produk & kategori obat
- 🛒 Transaksi penjualan cepat dengan keranjang otomatis
- 📊 Dashboard interaktif untuk memantau penjualan
- 🧾 Sistem member & poin 
- 🏷️ Barcode generator otomatis/input manual
- 🖨️ Cetak invoice & laporan PDF
- 📲 Kirim struk via WhatsApp untuk transaksi member
- 🔍 Transaksi dengan scan QR code

---

## 🛠️ Teknologi yang Digunakan

- **Backend:** PHP 8 + MySQLi
- **Frontend:** HTML, TailwindCSS, JavaScript
- **Library:** [picqer/php-barcode-generator](https://github.com/picqer/php-barcode-generator)
- **Database:** MySQL

---

## 📥 Instalasi

1. **Clone Repository**
   ```bash
   git clone https://github.com/indhclsta/medical-pos.git
   cd medical-pos



