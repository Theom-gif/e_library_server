**Update Profile — Frontend Integration Guide**

- **Purpose:** Document the frontend flow, form shape, validation, and API calls required to implement "Update Profile" in the web/mobile client.
- **Location:** This file documents routes defined in the backend and the recommended frontend behavior.

**Overview**
- Feature: allow authenticated users to view and edit their profile, upload an avatar image, and change password.
- Assumptions: frontend authenticates with the backend (Sanctum or bearer token) and can send JSON and multipart/form-data requests.

**Relevant API Endpoints (authenticated)**
- `GET /api/me` or `GET /api/me/profile` — returns current user profile payload via `ProfileController@getCurrentUser`.
- `POST|PUT|PATCH /api/me/profile` — updates profile fields via `ProfileController@updateProfile`. Accepts JSON and also accepts file fields (`avatar` or `avatar_file`) if sent as multipart/form-data.
- `POST /api/me/avatar` — new endpoint added for dedicated avatar uploads via `ProfileController@uploadAvatar`. Accepts multipart/form-data with `avatar` or `avatar_file` file field; returns `avatar` path and `avatar_url`.
- `PATCH /api/auth/update-profile` — alternate update endpoint (exists in routes) — maps to same controller action.
- `POST /api/auth/change-password` — change password via `ProfileController@changePassword` (fields: `current_password`, `new_password`).

Notes:
- The project normalizes avatar paths using `PublicImage::normalize`. The user model stores an `avatar` string (path or URL). The backend returns `avatar_url` in the profile payload.

**Frontend Responsibilities**
- Fetch profile on mount and populate fields using `GET /api/me`.
- Provide a form with fields: `firstname`, `lastname`, `bio`, `facebook_url` (backend expects these names), avatar file input, and a separate password-change form.
- Show avatar preview when user selects an image.
- Validate client-side before sending: required fields (as per your UX), valid email if you display email editing, image type and size (recommend max 5MB).

**Avatar Upload Strategies (choose one)**
- Atomic flow (recommended):
  1. Upload selected avatar first to `POST /api/me/avatar` using `FormData`.
  2. Receive `avatar` path/`avatar_url` and include `avatar` value in the subsequent `PUT /api/me/profile` JSON payload (or rely on the single avatar route to persist it).
- Single-request flow: submit the profile update as multipart/form-data to `POST /api/me/profile` including the file under `avatar` or `avatar_file`.
- Recommended compatibility note: use `POST /api/me/profile` for file uploads. Many frontend stacks and PHP servers are less reliable with multipart file uploads sent as raw `PUT/PATCH`.

**Request Examples**
- JSON profile update (no file):

  Endpoint: `PUT /api/me/profile`
  Body (JSON):
  {
    "firstname": "Jane",
    "lastname": "Doe",
    "bio": "Reader and writer",
    "facebook_url": "https://facebook.com/jane"
  }

- Avatar upload (multipart/form-data):

  Endpoint: `POST /api/me/avatar`
  FormData keys:
  - `avatar` (file) — image file (png/jpeg), max 5MB

  Response (200):
  {
    "success": true,
    "message": "Avatar uploaded successfully",
    "data": { "avatar": "avatars/abcd1234.jpg", "avatar_url": "https://.../storage/avatars/abcd1234.jpg" }
  }

- Profile update with avatar in one request:
  - send `POST /api/me/profile` with `multipart/form-data` containing `avatar` file and text fields.

- Change password:
  Endpoint: `POST /api/auth/change-password`
  Body (JSON):
  {
    "current_password": "oldpass",
    "new_password": "newpass123"
  }

**Frontend Implementation Notes**
- Use `axios` or `fetch` and include auth token or cookies used by Sanctum.
- Example avatar upload using `axios`:

  const formData = new FormData();
  formData.append('avatar', file);
  const res = await axios.post('/api/me/avatar', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  });

- Example profile update (JSON):

  await axios.put('/api/me/profile', {
    firstname, lastname, bio, facebook_url, avatar: avatarPathOptional
  });

- Error handling: map backend validation errors returned as JSON to field-level messages. Backend uses a structure like `{ success: false, message: '...', errors: {...} }`.

**Validation & UX**
- Client-side: validate image MIME (`image/png`, `image/jpeg`), file size <= 5MB, and simple text constraints.
- Disable Save while requests are in-flight.
- Show inline field errors and a toast/snackbar for success or server errors.
- Warn user about unsaved changes if navigating away.

**Accessibility & i18n**
- Use proper `<label for>` and `alt` text for avatar images.
- Wrap UI strings with your i18n system to enable localization.

**Testing**
- Unit test form validators and hooks.
- Integration test with mocked API: assert `GET` populates fields, `PUT` sends correct payload, `POST /me/avatar` uploads file and sets preview.
- E2E tests: update name + avatar and verify UI reflects server response.

**Files / Components Suggestion**
- `ProfileForm` — main profile editor.
- `AvatarUploader` — handles preview, drag/drop, and upload.
- `ChangePasswordForm` — separate form for password updates.
- `useUserProfile` hook — centralize API calls and optimistic updates.

**Next steps**
- If you want, I can add a ready-to-copy React component and example `axios` calls implementing the flows above.

---

Generated by your backend changes; if you want this saved in another location or formatted as a frontend README, tell me where to put it or say "react" to get a component example.
