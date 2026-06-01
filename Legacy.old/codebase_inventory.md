# Codebase Inventory: Controllers & Views

| Controller | Method | Route | Category | View/Template |
| :--- | :--- | :--- | :--- | :--- |
| `HomeController` | `index` | `/` | Hub-Owned | `home` |
| `AuthController` | `showLogin` | `/login` | Hub-Owned | `login` (Missing file) |
| `ServicesController` | `index` | `/services` | Spoke (Services) | `services/index` |
| `ServicesController` | `show` | `/services/{id}` | Spoke (Services) | `services/epub-font-changer` |
| `TestController` | `morph` | `/test/morph` | Hub-Owned (Dev) | `test.morph` |
| `ActionController` | `handle` | `/_superpowers/action` | Hub-Owned (Core) | N/A |

# Independent App Candidates (Spokes)
- **MangaScript**: (`app/Services/MangaScript/`)
- **EPUB Font Changer**: Currently in `ServicesController` and `app/Services/EpubFontChanger/`.
