<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

if (empty($_SESSION['usuario_id'])) {
    redirectTo('login.php');
}

function currentUserId(): int
{
    return (int) $_SESSION['usuario_id'];
}

function currentUserName(): string
{
    return (string) ($_SESSION['usuario_nome'] ?? '');
}

function currentPerfil(): string
{
    return (string) ($_SESSION['perfil'] ?? '');
}

function requirePerfil(string ...$perfis): void
{
    if (!in_array(currentPerfil(), $perfis, true)) {
        http_response_code(403);
        exit('Acesso negado para este perfil.');
    }
}

/**
 * @return array{sql: string, params: array<string, int>}
 */
function propriedadeScope(string $alias = 'p'): array
{
    $perfil = currentPerfil();
    $userId = currentUserId();

    if ($perfil === 'admin') {
        return ['sql' => '1 = 1', 'params' => []];
    }

    if ($perfil === 'proprietario') {
        return [
            'sql' => $alias . '.proprietario_id = :rbac_uid',
            'params' => ['rbac_uid' => $userId],
        ];
    }

    if ($perfil === 'tecnico') {
        return [
            'sql' => $alias . '.proprietario_id IN (SELECT tc.cliente_id FROM tecnico_cliente tc WHERE tc.tecnico_id = :rbac_uid)',
            'params' => ['rbac_uid' => $userId],
        ];
    }

    return ['sql' => '0 = 1', 'params' => []];
}

function userCanAccessProperty(PDO $pdo, int $propertyId): bool
{
    $scope = propriedadeScope('p');
    $statement = $pdo->prepare(
        'SELECT p.id FROM propriedades p WHERE p.id = :id AND ' . $scope['sql']
    );
    $statement->execute(['id' => $propertyId] + $scope['params']);

    return (bool) $statement->fetchColumn();
}

function userCanAccessTalhao(PDO $pdo, int $talhaoId): bool
{
    $scope = propriedadeScope('p');
    $statement = $pdo->prepare(
        'SELECT t.id
         FROM talhoes t
         INNER JOIN propriedades p ON p.id = t.propriedade_id
         WHERE t.id = :id AND ' . $scope['sql']
    );
    $statement->execute(['id' => $talhaoId] + $scope['params']);

    return (bool) $statement->fetchColumn();
}

function userCanAccessAnalise(PDO $pdo, int $analiseId): bool
{
    $scope = propriedadeScope('p');
    $statement = $pdo->prepare(
        'SELECT a.id
         FROM analises_solo a
         INNER JOIN talhoes t ON t.id = a.talhao_id
         INNER JOIN propriedades p ON p.id = t.propriedade_id
         WHERE a.id = :id AND ' . $scope['sql']
    );
    $statement->execute(['id' => $analiseId] + $scope['params']);

    return (bool) $statement->fetchColumn();
}

/**
 * @return list<array<string, mixed>>
 */
function fetchAccessibleOwners(PDO $pdo): array
{
    if (currentPerfil() === 'admin') {
        return $pdo->query(
            "SELECT id, nome, email FROM usuarios WHERE perfil = 'proprietario' ORDER BY nome"
        )->fetchAll();
    }

    if (currentPerfil() === 'tecnico') {
        $statement = $pdo->prepare(
            "SELECT u.id, u.nome, u.email
             FROM usuarios u
             INNER JOIN tecnico_cliente tc ON tc.cliente_id = u.id
             WHERE tc.tecnico_id = :uid AND u.perfil = 'proprietario'
             ORDER BY u.nome"
        );
        $statement->execute(['uid' => currentUserId()]);

        return $statement->fetchAll();
    }

    $statement = $pdo->prepare(
        'SELECT id, nome, email FROM usuarios WHERE id = :uid'
    );
    $statement->execute(['uid' => currentUserId()]);

    return $statement->fetchAll();
}

function denyAccess(): never
{
    http_response_code(403);
    exit('Acesso negado.');
}
