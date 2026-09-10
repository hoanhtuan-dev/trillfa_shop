# Thiết kế: Model theo nhóm công việc (task groups)

## Phân tích hiện trạng — các card / tính năng trong /studio và model chúng dùng

| Card / tính năng | Nhóm công việc | Hiện dùng gì | Vấn đề |
|---|---|---|---|
| **ConceptCard** (Tạo Ảnh 2D) | `image` | studio_model_candidates('image') + image_provider config | Không chọn model trên card; group "image" trộn cả model sinh ảnh lẫn model edit |
| **InpaintCard** (Sửa ảnh) | `edit` | inpaint_models từ defaults (filter isImageEditCapableModel) | Group "edit" KHÔNG tồn tại trong studio_models — chỉ lọc từ group image |
| **RefImageCard** (Ảnh mới từ ảnh mẫu / Thử đồ) | `image` (refgen) | provider/model nullable → default image | Không có selector trên card; hardcode null |
| **SwapCard** (Thay Đổi Người Mẫu) | `swap` | studio_swap_model() = swap_model setting hoặc qwen_edit_model | Không có group riêng; fallback chuỗi hardcoded |
| **DirectorCard** (Render video) | `video` | video_model setting + model_registry_id | videoModel store rỗng mặc định; không có selector |
| **StylistCard** (Thuật sỹ ảo) | `prompt` | studio_qwen_text_models() | Danh sách model từ comma-string setting — không thấy Model Registry |
| **SuggestCard** (Gợi ý từ ảnh) | `suggest` (vision) | studio_suggest_qwen_models() + suggest provider riêng | Cấu hình riêng hoàn toàn — không liên kết Model Registry |
| **faceDescription / poseDescription** (hỗ trợ tryon) | `vision` | studio_suggest_qwen_models() (mượn của suggest!) | Vision "mượn" cấu hình suggest — không độc lập |
| **translate** | `translate` | translate_model setting + hardcoded gemini fallbacks | Hardcoded chuỗi fallback |
| **GeminiService** (Giám đốc sáng tạo) | `prompt` | prompt_provider + prompt_model settings | Không thấy Model Registry |
| **ProductAIService** (AI Sản phẩm - admin) | `product_ai` | product_ai_* settings | Đã tách riêng, giữ nguyên |

## Nguyên tắc thiết kế (theo DSH)

1. **Mỗi nhóm công việc = một task group** có danh sách model riêng (từ Model Registry, lọc theo role)
2. **Model Registry là nguồn duy nhất** — một model đăng ký 1 lần, tham chiếu nhiều nhóm
3. **Số nhóm mở rộng**: image | edit | video | vision | prompt | translate | swap (7 nhóm hoạt động)
4. **Tab nhìn thấy nhau**: tab Models gán model vào nhóm; tab Cấu hình chọn default cho từng nhóm; các card /studio nhận đúng danh sách model của nhóm mình
5. **Không xóa trộn**: mọi setting cũ vẫn đọc được; task group chỉ là lớp gán role lên studio_models có sẵn

## Cấu trúc dữ liệu

```
studio_models.group: image|edit|video|vision|prompt|translate|swap|inference|text
```

Task-group resolution helper:
```
studio_task_group_models('edit')  →  các model edit-capable (group=edit + lọc từ image)
studio_task_group_models('vision') →  các model vision-capable
```

## Endpoint /studio/defaults mở rộng

Mỗi card nhận `task_groups.<name>.models` + `task_groups.<name>.default` — card render selector ngay trên UI.
