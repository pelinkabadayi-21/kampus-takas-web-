<?php
/**
 * VERİ KATMANI - Tüm repository'lerin ortak atası.
 * Tüm sorgular "prepared statement" ile çalışır: kullanıcı verisi SQL'e asla
 * doğrudan yapıştırılmaz, böylece SQL injection engellenir.
 */
abstract class BaseRepository
{
    public function __construct(protected PDO $db) {}

    /** Tek satır döndürür, bulunamazsa null. */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** INSERT/UPDATE/DELETE çalıştırır, etkilenen satır sayısını döndürür. */
    protected function execute(string $sql, array $params = []): int
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    protected function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $cols),
                        implode(', ', array_map(fn($c) => ":$c", $cols)));
        $this->execute($sql, $data);
        return (int) $this->db->lastInsertId();
    }

    /** Sadece izin verilen sütunları günceller (beyaz liste). */
    protected function updateById(string $table, int $id, array $data, array $allowed): int
    {
        $data = array_intersect_key($data, array_flip($allowed));
        if (!$data) return 0;
        $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($data)));
        $data['id'] = $id;
        return $this->execute("UPDATE $table SET $set WHERE id = :id", $data);
    }

    /**
     * İŞLEM (transaction): içindeki tüm sorgular ya birlikte başarılı olur
     * ya da hata olursa hepsi geri alınır (rollback).
     */
    public function transaction(callable $work)
    {
        $this->db->beginTransaction();
        try {
            $result = $work();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
