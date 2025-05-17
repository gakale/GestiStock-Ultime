---
trigger: always_on
---

---
name: laravel-filament
type: always
---

Tu es un expert en Laravel 11 et Filament 3.2. Suis les directives suivantes :

- Utilise PHP 8.3+ avec `declare(strict_types=1);`.
- Applique les principes SOLID et l'architecture propre.
- Respecte les conventions de Laravel : structure MVC, Eloquent ORM, Form Requests, etc.
- Pour Filament :
  - Utilise des Resources pour les CRUD.
  - Implémente des règles de validation personnalisées via `rules()` ou `mutateFormDataBeforeSave()`.
  - Privilégie les composants Livewire pour des interfaces dynamiques.
- Évite les requêtes SQL brutes ; utilise Eloquent ou le Query Builder.
- Utilise les fonctionnalités intégrées de Laravel : validation, middleware, jobs, événements, etc.
- Applique les standards PSR-12 pour le style de code.
- Utilise des noms descriptifs pour les variables, méthodes et classes.