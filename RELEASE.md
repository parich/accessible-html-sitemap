# ขั้นตอนการออก Version ใหม่

ปลั๊กอินนี้ออก 2 ช่องทางจากไฟล์ zip ชุดเดียวกัน:

- **GitHub Release** — zip สำหรับดาวน์โหลด (ไม่มีระบบอัปเดตจาก GitHub ในตัวปลั๊กอิน เพราะกฎข้อ 8 ของ WordPress.org ห้าม)
- **WordPress.org (SVN)** — ช่องทางหลัก เว็บที่ติดตั้งไม่ว่าจากช่องทางไหนจะได้รับอัปเดตจาก WordPress.org เพราะ slug และชื่อโฟลเดอร์เดียวกัน

> **ครั้งแรก (1.0.0):** ส่ง zip เข้ารีวิวที่ <https://wordpress.org/plugins/developers/add/> ก่อน (ต้องเปิด 2FA ในบัญชี `parich`) แล้วออก GitHub Release และ commit ขึ้น SVN **หลังได้รับอนุมัติ** — ถ้าผู้ตรวจขอให้แก้ จะได้ไม่มี 1.0.0 สองชุดที่ไม่เหมือนกัน ระหว่างรอให้ตอบอีเมลจาก `plugins@wordpress.org` ใน thread เดิมเท่านั้น ห้ามส่งซ้ำ

---

## 1. แก้ไข Code และ Changelog

- แก้ใน `src/`, `includes/`, `admin/` หรือ `parich-accessible-sitemap.php`
- เพิ่มรายการใน `== Changelog ==` ของ `readme.txt` (และ `== Upgrade Notice ==` ถ้ามีเรื่องที่ผู้ใช้ต้องรู้ก่อนอัปเดต)
- ข้อความใหม่ต้องแปลไทย: `npm run build && npm run i18n` จะบอกข้อความที่ยังไม่ได้แปลใน `languages/parich-accessible-sitemap-th.po` (ไฟล์นี้ไม่อยู่ใน zip ใช้สำหรับ test และนำเข้า translate.wordpress.org — หลังออกเวอร์ชันใหม่ ให้อัปโหลดคำแปลใหม่ที่ <https://translate.wordpress.org/projects/wp-plugins/parich-accessible-sitemap/>)

---

## 2. อัปเดต Version Number (3 ที่ ต้องตรงกัน)

| ไฟล์ | บรรทัด |
|---|---|
| `parich-accessible-sitemap.php` | ` * Version:           1.0.1` |
| `readme.txt` | `Stable tag: 1.0.1` |
| `package.json` | `"version": "1.0.1"` |

- ตรวจ `Tested up to:` ใน `readme.txt` ให้ตรงกับ WordPress รุ่นล่าสุดที่ **ทดสอบจริงแล้ว** (ห้ามเกินรุ่น RC ปัจจุบัน)
- Plugin Check จะ error ถ้าเวอร์ชันในไฟล์หลักกับ `Stable tag` ไม่ตรงกัน

> **หลักการตั้ง version (Semantic Versioning):** `MAJOR.MINOR.PATCH`
> - **PATCH** (`1.0.x`) — แก้ bug
> - **MINOR** (`1.x.0`) — เพิ่ม feature ใช้ร่วมกับของเดิมได้
> - **MAJOR** (`x.0.0`) — เปลี่ยนแปลงใหญ่

---

## 3. Build, Lint, Test

```bash
npm run build
npm run i18n
npm run lint:js && npm run lint:css
npm test
```

---

## 4. สร้าง ZIP และตรวจด้วย Plugin Check

```bash
npm run plugin-zip
```

แตก zip ไว้นอกเว็บ แล้วตรวจสิ่งที่จะส่งออกไปจริง (Plugin Check ติดตั้งอยู่บนเว็บ local):

```bash
wp plugin check <โฟลเดอร์ที่แตก zip>/parich-accessible-sitemap --slug=parich-accessible-sitemap --include-experimental
```

ต้องได้ `Success: Checks complete. No errors found.`

> **Plugin Check ผ่านยังไม่พอ** — รีวิวรอบแรก (2026-09) โดนตีกลับทั้งที่ Plugin Check ผ่าน ตรวจเรื่องที่ฝั่งรีวิวดูเพิ่มด้วย
> (ถ้ามี skill `wporg-plugin-submission`: `node ~/.claude/skills/wporg-plugin-submission/scripts/precheck.mjs . --zip parich-accessible-sitemap.zip`):
> - ไม่มี `load_plugin_textdomain()` / `Domain Path` และไม่มีไฟล์แปลใน zip
> - `register_setting()` ทุกตัวมี `'type'` และ `'sanitize_callback'` เขียนตรง ๆ (ไม่ผ่านตัวแปร/ลูป)
> - `Tested up to` = WordPress รุ่นล่าสุดที่ทดสอบจริง, `Stable tag` = Version
> - ชื่อยังขึ้นต้นด้วยคำเฉพาะตัว; repo ไม่มี path ในเครื่องหรืออีเมลส่วนตัวใน commit

จดค่า SHA-256 ของ zip ไว้ ไฟล์ที่แนบใน GitHub Release และที่ส่ง WordPress.org ต้องเป็นไฟล์เดียวกัน:

```bash
sha256sum parich-accessible-sitemap.zip
```

---

## 5. Commit และ Push

```bash
git add -A
git commit -m "release: 1.0.1"
git push origin main
```

---

## 6. สร้าง GitHub Release

```bash
gh release create v1.0.1 parich-accessible-sitemap.zip \
  --title "1.0.1" \
  --notes "## Changelog
- ..."
```

> Tag บน GitHub (`v1.0.1`) ต้องตรงกับ Version ในไฟล์หลัก (`1.0.1`)

---

## 7. ส่งขึ้น WordPress.org (SVN)

ใช้ username `parich` (ตัวพิมพ์ต้องตรง) กับ **SVN password** ที่ตั้งไว้ใน <https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password> (ไม่ใช่รหัสผ่าน login)

```bash
svn checkout https://plugins.svn.wordpress.org/parich-accessible-sitemap svn-ahs

# trunk = ไฟล์ข้างใน zip (ไม่ใส่ตัว zip และไม่ซ้อนโฟลเดอร์ parich-accessible-sitemap/)
rm -rf svn-ahs/trunk/* && unzip -q parich-accessible-sitemap.zip -d zip-tmp
cp -R zip-tmp/parich-accessible-sitemap/. svn-ahs/trunk/ && rm -rf zip-tmp

# assets = ไอคอน แบนเนอร์ และภาพหน้าจอ (ไม่อยู่ใน zip)
cp .wordpress-org/* svn-ahs/assets/

cd svn-ahs
svn add --force trunk assets
svn status | grep '^!' | awk '{print $2}' | xargs -r svn rm   # ไฟล์ที่ถูกลบ
svn cp trunk tags/1.0.1                                        # ชื่อ tag ไม่มี v
svn commit -m "Release 1.0.1" --username parich
```

> **ระวัง:** commit แล้วขึ้นเว็บทันที ไม่มีปุ่มปิด — `trunk/readme.txt` ต้องมี `Stable tag` ตรงกับ tag ที่เพิ่งสร้าง
> ถ้าแก้แค่ readme หรือรูปใน assets ไม่ต้องออกเวอร์ชันใหม่ commit เฉพาะไฟล์นั้นได้

---

## 8. ตรวจสอบ

- หน้าปลั๊กอิน <https://wordpress.org/plugins/parich-accessible-sitemap/> แสดงเวอร์ชันใหม่ (อาจใช้เวลาหลายนาทีถึง 6 ชั่วโมง)
- เว็บที่ติดตั้งไว้เห็นอัปเดตใน Dashboard → Updates (ตรวจทันทีได้ที่ `/wp-admin/update-core.php?force-check=1`)
- GitHub: `gh release view v1.0.1` มีไฟล์ zip แนบ
