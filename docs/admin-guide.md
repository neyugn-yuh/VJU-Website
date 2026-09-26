# Hướng dẫn sử dụng VJU CMS (dành cho biên tập viên)

Địa chỉ quản trị: `https://vju.ac.vn/admin` — đăng nhập bằng **tài khoản Google @vju.ac.vn**.
Lần đầu đăng nhập, tài khoản được tạo nhưng **chưa có quyền**; Quản trị viên cần gán vai trò trước khi bạn dùng được.

## Vai trò

| Vai trò | Được phép |
|---|---|
| Admin | Toàn quyền: người dùng, vai trò, cài đặt, nhật ký, xóa vĩnh viễn, công cụ di chuyển dữ liệu |
| Editor | Mọi nội dung: sửa, duyệt, xuất bản, hẹn giờ, chuyên mục/thẻ, media, menu, bình luận, SEO |
| Author | Bài của mình: tạo, sửa, xóa, xuất bản, hẹn giờ; tải media |
| Contributor | Bài của mình: tạo, sửa khi còn nháp, **gửi duyệt** (không tự xuất bản) |

## Viết và xuất bản bài

1. **Content → Posts → New post**.
2. Mỗi ngôn ngữ là một tab (Tiếng Việt / English / 日本語). Chỉ điền ngôn ngữ nào thực sự có bản dịch —
   để trống tiêu đề nghĩa là bản dịch đó không tồn tại (trang ngôn ngữ đó sẽ báo 404, không hiển thị ngôn ngữ khác).
3. *Slug* để trống sẽ tự tạo từ tiêu đề (bỏ dấu tiếng Việt).
4. Soạn nội dung trong trình soạn thảo: tiêu đề H2/H3, bảng, trích dẫn, danh sách, chèn ảnh (📎 tải lên
   hoặc 🖼 "Media library" để chọn file có sẵn). Dán link YouTube thành một đoạn riêng → tự hiển thị video.
5. Cột phải: **Trạng thái** (chỉ hiện các trạng thái bạn được phép), ngày đăng, chuyên mục, thẻ, ảnh đại diện.
6. **Lưu**. Hệ thống tự lưu nháp mỗi 20 giây ("Autosaved … ago"); nếu trình duyệt bị đóng, mở lại bài và bấm
   **Restore autosave**.

Trạng thái: *Draft* → *Pending review* (gửi duyệt) → *Published* / *Scheduled* (hẹn giờ, tự đăng đúng giờ) / *Private*.
Nút **View** mở bài (hoặc bản xem trước nếu chưa đăng — không bị Google lập chỉ mục).

## Lịch sử phiên bản

Mỗi lần lưu tạo một phiên bản (tab **Revision history** cuối trang sửa bài). **View** để xem, **Restore** để
khôi phục — phiên bản hiện tại luôn được giữ lại trước khi khôi phục, không mất dữ liệu.

## Thùng rác

*Move to trash* đưa bài vào thùng rác (bộ lọc **Trashed**), có thể **Restore**. Chỉ Admin được **Force delete**
(xóa vĩnh viễn, không khôi phục được).

## Trang và "content modules"

**Content → Pages**: trang có thể có trang cha (đường dẫn dạng `/tuyen-sinh/hoc-phi/`) và *template*
(Admissions, Education, Research, Landing, Contact). Phần **Content modules** cho phép ghép các khối: Hero (ảnh
bìa), Rich text, Cards, Key figures, Latest posts (tự lấy tin theo chuyên mục), Steps (quy trình), FAQ, Document
downloads, Call to action, Partners/logos, Video. Kéo để sắp xếp, mỗi ngôn ngữ có bộ khối riêng.

Trang chủ là một trang được chọn tại **Settings → Site → Homepage content**.

## Nội dung có cấu trúc

**Structured content**: *Download documents*, *Notifications*, *Tuition fee notices*, *Job opportunities* — có thêm
ô file đính kèm, số hiệu, ngày ban hành, hạn chót, địa điểm, link ứng tuyển.

## Media

**Content → Media library**: xem dạng lưới/danh sách, lọc theo loại/ngày, lọc "Images without ALT".
**Upload** nhiều file một lúc (JPG, PNG, WebP, GIF, PDF, Word, Excel, PowerPoint, MP4; tối đa 64 MB).
File trùng (cùng nội dung) được dùng lại, không tạo bản sao. Luôn điền **ALT text** cho ảnh có nội dung.

## Chuyên mục, thẻ, menu

- **Taxonomy → Categories/Tags**: tên và slug theo từng ngôn ngữ; chuyên mục có thể lồng nhau.
- **Site → Menus**: mỗi vị trí (Header, Top bar, Footer) × mỗi ngôn ngữ là một menu. Kéo thả để sắp xếp,
  "Sub-items" để tạo menu con. Mục trỏ tới bài chưa đăng sẽ tự ẩn.
- **Site → Redirects**: chuyển hướng URL cũ (301/302) hoặc báo "410 Gone". Đổi slug bài đã đăng sẽ tự tạo 301.

## SEO

Trong mỗi tab ngôn ngữ của bài có mục **SEO**: meta title/description, canonical, ảnh Open Graph, cho phép index.
Mặc định lấy từ tiêu đề, tóm tắt, ảnh đại diện. Cài đặt chung: **Settings → SEO**.
*Discourage search engines* chỉ bật trên môi trường staging.

## Bình luận

**Content → Comments**: bình luận mới ở trạng thái *Pending*; **Approve**, **Spam**, **Trash** hoặc xóa.
