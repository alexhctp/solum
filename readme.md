# Solum

Este projeto foi desenvolvido como parte das atividades da disciplina Gestão de Processos e Resultados na Produção do Café, sob orientação da tutora Marcela Pereira.

A aplicação foi elaborada com o apoio de ferramentas de Inteligência Artificial e com base em conceitos de programação orientada a especificações, buscando transformar requisitos técnicos e agronômicos em funcionalidades de software.

As interpretações e recomendações apresentadas possuem caráter acadêmico e informativo, não substituindo a avaliação de um profissional habilitado em Agronomia.

## Sobre o projeto Solum

Aplicacao web PHP para cadastro, interpretacao e acompanhamento de analises de fertilidade do solo.

O projeto possui:

- Cadastro de propriedades, talhoes e analises de solo.
- Interpretacao de SB, CTC a pH 7, V%, necessidade de calagem e relacao Ca:Mg.
- Alertas de antagonismo K x Mg e recomendacao de parcelamento de K em CTC baixa.
- Painel de Gestão com indicadores e graficos.
- Laudo tecnico para impressao ou exportacao em PDF.
- MySQL com chaves estrangeiras, indices e restricoes de integridade.

## Requisitos

- Linux ou outra plataforma compativel com PHP.
- PHP 8.1 ou superior.
- Extensao `pdo_mysql` habilitada.
- MySQL 8.0 ou superior.
- Git 2.x, caso queira sincronizar o projeto com o GitHub.
- Navegador moderno com acesso a internet para carregar Bootstrap e Chart.js via CDN.

Versoes verificadas neste ambiente:

```text
PHP 8.4.24
MySQL 8.4.11
Git 2.47.3
```

## Estrutura

```text
.
├── analises_solo.php          # CRUD de analises de solo
├── login.php                  # Autenticacao
├── logout.php                 # Encerramento da sessao
├── dashboard.php             # Painel e escopo RBAC
├── cadastro_usuario.php      # Cadastro de usuarios e vinculos
├── cadastro_propriedade.php  # Cadastro de propriedades
├── propriedades.php          # Atalho para cadastro de propriedades
├── talhoes.php               # CRUD de talhoes
├── auth.php                  # Protecao e regras de escopo
├── relatorio_laudo.php       # Laudo individual para impressao/PDF
├── config/
│   └── database.php           # Conexao PDO
├── database/
│   └── schema.sql             # Banco, tabelas, indices e FKs
└── src/
    ├── bootstrap.php          # Helpers, sessao, CSRF e layout comum
    └── InterpretadorSoloEngine.php
```

## Instalacao

### 1. Obter o projeto

Para usar o repositorio GitHub:

```bash
git clone https://github.com/alexhctp/solum.git
cd solum
```

Se o projeto ja estiver na VM:

```bash
cd /var/www/solum
git pull origin main
```

### 2. Instalar dependencias do sistema

Em Debian ou Ubuntu:

```bash
sudo apt update
sudo apt install -y php php-cli php-mysql mysql-client git
```

Confirme a extensao PDO para MySQL:

```bash
php -m | grep -E 'PDO|pdo_mysql'
```

A saida deve conter `PDO` e `pdo_mysql`.

### 3. Criar o banco e as tabelas

Execute o schema com uma conta administrativa do MySQL:

```bash
mysql -u root -p < database/schema.sql
```

O script cria o banco `solum` com as tabelas:

- `usuarios`
- `tecnico_cliente`
- `propriedades`
- `talhoes`
- `analises_solo`
- `recomendacoes`

O mesmo script carrega dados de teste: um administrador, um tecnico, dois
proprietarios, dois vinculos tecnico-cliente e duas propriedades. A senha de
todos os usuarios de teste e `Solum@123`.

### 4. Criar o usuario da aplicacao

Use uma conta administrativa e substitua `SENHA_FORTE` por uma senha segura:

```sql
CREATE USER IF NOT EXISTS 'solum'@'127.0.0.1' IDENTIFIED BY 'SENHA_FORTE';
ALTER USER 'solum'@'127.0.0.1' IDENTIFIED BY 'SENHA_FORTE';
GRANT ALL PRIVILEGES ON solum.* TO 'solum'@'127.0.0.1';
FLUSH PRIVILEGES;
```

O usuario da aplicacao tem acesso somente ao banco `solum` pelo host local.

### 5. Configurar o ambiente

A classe PDO le as variaveis abaixo:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=solum
export DB_USER=solum
export DB_PASS='SENHA_FORTE'
```

Nao coloque credenciais no codigo ou no Git. O arquivo `.env` e ignorado pelo Git, mas a aplicacao atual usa variaveis de ambiente do processo. Em producao, configure-as no servico que inicia o PHP ou no gerenciador de processos usado pela VM.

Teste a conexao diretamente:

```bash
MYSQL_PWD='SENHA_FORTE' mysql --protocol=tcp \
  -h 127.0.0.1 -u solum -D solum -e 'SHOW TABLES;'
```

## Executar em desenvolvimento

Na raiz do projeto:

```bash
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_NAME=solum \
DB_USER=solum \
DB_PASS='SENHA_FORTE' \
php -S 127.0.0.1:8088 -t .
```

Acesse localmente:

```text
http://127.0.0.1:8088/dashboard.php
```

Paginas principais:

- `login.php`: entrada da aplicacao.
- `dashboard.php`: indicadores, graficos e alertas.
- `cadastro_usuario.php`: cadastro de usuarios e vinculo opcional de tecnicos.
- `cadastro_propriedade.php`: cadastro com proprietario escolhido por admin/tecnico ou automatico para proprietario.
- `propriedades.php`: redirecionamento para o cadastro de propriedades.
- `talhoes.php`: cadastro de talhoes vinculados a propriedades.
- `analises_solo.php`: cadastro de resultados da analise.
- `relatorio_laudo.php?analise_id=1`: laudo de uma analise existente.

## Testar autenticacao e RBAC

Depois de iniciar o servidor, acesse `http://127.0.0.1:8088/login.php` com
uma destas contas:

| Perfil | E-mail |
| --- | --- |
| Admin | `admin@solum.test` |
| Tecnico | `tecnico@solum.test` |
| Proprietario | `carlos@solum.test` ou `marina@solum.test` |

Use `Solum@123` como senha. O administrador visualiza todos os usuarios e
propriedades e pode cadastrar usuarios. O tecnico visualiza apenas os dois
proprietarios vinculados e suas propriedades. Cada proprietario visualiza
somente seus proprios dados e propriedades; ao cadastrar uma propriedade, o
vinculo e feito automaticamente pelo usuario da sessao.

## Acesso remoto pela rede

Para disponibilizar o servidor PHP na rede da VM, escute em todas as interfaces:

```bash
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_NAME=solum \
DB_USER=solum \
DB_PASS='SENHA_FORTE' \
php -S 0.0.0.0:8088 -t /var/www/solum
```

Se o IP da VM for `192.168.185.251`, acesse:

```text
http://192.168.185.251:8088/dashboard.php
```

Verifique se a porta esta escutando:

```bash
ss -ltnp | grep ':8088'
```

Se a rede nao permitir acesso direto, use tunel SSH a partir da sua maquina local:

```bash
ssh -L 8088:127.0.0.1:8088 usuario@192.168.185.251
```

Depois acesse:

```text
http://127.0.0.1:8088/dashboard.php
```

Para um ambiente de producao, prefira Nginx ou Apache com HTTPS e PHP-FPM. O servidor embutido do PHP e destinado a desenvolvimento e testes.

## Interpretacao agronomica

A classe `src/InterpretadorSoloEngine.php` recebe um array com os campos da tabela `analises_solo`:

```php
require_once __DIR__ . '/src/InterpretadorSoloEngine.php';

$engine = new InterpretadorSoloEngine(
    prnt: 80.0,
    vAlvo: 60.0
);

$resultado = $engine->interpretar($analise);
```

Calculos implementados:

- K em `mg/dm3` convertido para `cmolc/dm3` dividindo por 390.
- Soma de bases: `Ca + Mg + K`.
- CTC a pH 7: `SB + H+Al`.
- V% atual: `(SB / CTC) * 100`.
- Necessidade de calagem com PRNT e V% alvo configuraveis.
- Relacao Ca:Mg, alerta para K elevado e parcelamento de K em CTC baixa.

As faixas de interpretacao sao referencias gerais. A recomendacao final deve considerar cultura, produtividade esperada, textura, historico de manejo, profundidade e tabelas regionais.

## Seguranca

- Use prepared statements para consultas com dados de entrada.
- Mantenha `DB_PASS` fora do repositorio.
- Use HTTPS em producao.
- Restrinja o usuario MySQL ao banco da aplicacao.
- Mantenha PHP, MySQL e sistema operacional atualizados.
- Troque imediatamente qualquer senha que tenha sido exposta em terminal, chat ou historico.

## Validacao

Verificar sintaxe dos arquivos PHP:

```bash
php -l config/database.php
php -l src/bootstrap.php
php -l src/InterpretadorSoloEngine.php
php -l dashboard.php
php -l propriedades.php
php -l talhoes.php
php -l analises_solo.php
php -l relatorio_laudo.php
```

Verificar o estado do Git:

```bash
git status --short --branch
```

## GitHub

O repositorio remoto atual e:

```text
https://github.com/alexhctp/solum.git
```

Para publicar alteracoes:

```bash
git add .
git commit -m "Descreva a alteracao"
git push origin main
```

O diretorio `.github/` esta listado no `.gitignore`. Arquivos que ja estavam rastreados continuam versionados; a regra impede apenas novos arquivos nao rastreados nesse diretorio.
