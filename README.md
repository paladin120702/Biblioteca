# Biblioteca API

[![CI](https://github.com/paladin120702/Biblioteca/actions/workflows/ci.yml/badge.svg)](https://github.com/paladin120702/Biblioteca/actions/workflows/ci.yml)

API REST para gestão de uma biblioteca: catálogo de livros, autores e categorias, cadastro de membros e controle de empréstimos com prazo de devolução e multa por atraso.

A API responde somente JSON, não tem front-end.

## Tecnologias

- PHP 8.3+ e Laravel 13
- Laravel Sanctum (autenticação por token)
- SQLite (desenvolvimento e testes), compatível com MySQL
- Pest (testes), Laravel Pint (estilo) e Larastan nível 9 (análise estática)
- GitHub Actions (CI)

## Arquitetura

O projeto segue MVC, usando os recursos do próprio Laravel em vez de camadas extras (Services, Repositories etc.).

| Camada | Onde | Papel |
|---|---|---|
| Model | `app/Models` | Dados, relacionamentos, scopes e regras de negócio, como `User::canBorrow()`, `Book::availableCopies()` e `Loan::calculateFine()` |
| View | `app/Http/Resources` | Formato do JSON de resposta (API Resources) |
| Controller | `app/Http/Controllers` | Recebe a requisição, chama o Model e devolve o Resource |

Apoio:

- `app/Http/Requests`: validação (Form Requests)
- `app/Policies`: autorização (quem pode fazer o quê)
- `app/Enums`: enums do PHP 8 (`Role`, `LoanStatus`, `LoanDenial`)
- `app/Console/Commands`: comando `loans:overdue`

## Regras de negócio

1. Um membro pode ter no máximo **3** empréstimos ativos.
2. Exemplares disponíveis = total de exemplares − empréstimos ativos do livro. Sem exemplar, sem empréstimo.
3. Um membro não pode pegar o mesmo livro duas vezes ao mesmo tempo.
4. O prazo de devolução é de **14 dias**.
5. A multa é de **R$ 1,00 por dia de atraso**, calculada e gravada na devolução.
6. Membro com empréstimo atrasado não pode fazer novo empréstimo.
7. Só admin cria, edita e exclui livros, autores e categorias. Qualquer usuário autenticado consulta o catálogo.
8. Membro vê e devolve apenas os próprios empréstimos. Admin vê todos.
9. Livro com empréstimo ativo e autor com livros não podem ser excluídos (`409 Conflict`). Livros usam soft delete.

Empréstimo e devolução rodam dentro de uma transação com `lockForUpdate()`, para que dois pedidos simultâneos não levem o último exemplar.

Os valores das regras 1, 4 e 5 ficam em `config/library.php` e podem ser alterados pelo `.env`:

```env
LIBRARY_MAX_ACTIVE_LOANS=3
LIBRARY_LOAN_DAYS=14
LIBRARY_FINE_PER_DAY_CENTS=100
```

## Como rodar localmente

Requisitos: PHP 8.3+ (com as extensões `pdo_sqlite`, `mbstring` e `intl`) e Composer.

```bash
git clone https://github.com/paladin120702/Biblioteca.git
cd Biblioteca
composer setup
php artisan db:seed
php artisan serve
```

O `composer setup` instala as dependências, cria o `.env`, gera a chave da aplicação e cria o banco SQLite com as tabelas. O seeder cria 1 admin, 3 membros, 10 autores, 5 categorias e 30 livros.

Usuário admin do seeder: `admin@biblioteca.test` / `password`.

Para usar MySQL, ajuste `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` no `.env` e rode `php artisan migrate --seed`.

## Endpoints

Todas as rotas têm o prefixo `/api`. As rotas autenticadas exigem o header `Authorization: Bearer {token}`.

| Método | Rota | Acesso |
|---|---|---|
| POST | `/register` | público |
| POST | `/login` | público |
| POST | `/logout` | autenticado |
| GET | `/me` | autenticado |
| GET | `/books`, `/books/{id}` | autenticado |
| POST | `/books` | admin |
| PUT/PATCH | `/books/{id}` | admin |
| DELETE | `/books/{id}` | admin |
| GET | `/authors`, `/authors/{id}` | autenticado |
| POST, PUT/PATCH, DELETE | `/authors`, `/authors/{id}` | admin |
| GET | `/categories`, `/categories/{id}` | autenticado |
| POST, PUT/PATCH, DELETE | `/categories`, `/categories/{id}` | admin |
| GET | `/loans` | membro (os seus) / admin (todos) |
| POST | `/loans` | membro |
| PATCH | `/loans/{id}/return` | dono do empréstimo / admin |

Filtros:

- `GET /api/books?search=machado&category=2&available=1`: busca por título ou autor, filtra por categoria e só livros com exemplar disponível. Paginado de 15 em 15 (`?page=2`).
- `GET /api/loans?status=active|overdue|returned`

Códigos de resposta: `201` na criação, `204` na exclusão e no logout, `401` sem token, `403` sem permissão, `404` não encontrado, `409` conflito e `422` erro de validação ou regra de negócio.

## Exemplos com curl

Login:

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@biblioteca.test", "password": "password"}'
```

```json
{
  "data": { "id": 1, "name": "Admin", "email": "admin@biblioteca.test", "role": "admin", "created_at": "..." },
  "token": "1|xxxxxxxx"
}
```

Listar livros disponíveis com filtro:

```bash
curl "http://127.0.0.1:8000/api/books?search=machado&available=1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

Pegar um livro emprestado (com o token de um membro):

```bash
curl -X POST http://127.0.0.1:8000/api/loans \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"book_id": 1}'
```

```json
{
  "data": {
    "id": 1,
    "book": { "id": 1, "title": "Dom Casmurro", "available_copies": 2, "...": "..." },
    "loaned_at": "2026-10-10T12:00:00.000000Z",
    "due_at": "2026-10-24T12:00:00.000000Z",
    "returned_at": null,
    "is_overdue": false,
    "days_late": 0,
    "fine_cents": 0,
    "fine": "R$ 0,00"
  }
}
```

Quando uma regra impede o empréstimo, a resposta é `422` com o motivo:

```json
{
  "message": "You have reached the maximum number of active loans.",
  "errors": { "book_id": ["You have reached the maximum number of active loans."] }
}
```

Devolver:

```bash
curl -X PATCH http://127.0.0.1:8000/api/loans/1/return \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

## Empréstimos atrasados

```bash
php artisan loans:overdue
php artisan loans:overdue --user=2
```

Lista em tabela os empréstimos atrasados com membro, livro, vencimento, dias de atraso e multa prevista. O comando está agendado para rodar todo dia às 8h; em produção, basta um cron chamando `php artisan schedule:run` a cada minuto.

## Testes e qualidade

Os testes usam SQLite em memória.

```bash
composer test      # Pest
composer lint      # Pint
composer analyse   # PHPStan (Larastan)
```

O CI roda Pint (`--test`), PHPStan e Pest em PHP 8.3 e 8.5 a cada push e pull request.
