<?php
/**
 * VERİ KATMANI - Tüm repository'lerin ortak atası.
 * Tüm sorgular "prepared statement" ile çalışır: kullanıcı verisi SQL'e asla
 * doğrudan yapıştırılmaz, böylece SQL injection engellenir.
 */
abstract class BaseRepository
{
    /** Veritabanı bağlantısı (PDO) alınır; tüm repository'ler bunu kullanır. */
    public function __construct(protected PDO $db) {}

    /** Tek satır döndürür, bulunamazsa null. */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        // İlk satırı al (yoksa false döner, biz null'a çeviriyoruz)
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Sorguyu çalıştırır ve bütün satırları dizi olarak döndürür. */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        // Bütün satırları dizi olarak döndür
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** INSERT/UPDATE/DELETE çalıştırır, etkilenen satır sayısını döndürür. */
    protected function execute(string $sql, array $params = []): int
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        // Kaç satırın etkilendiğini döndür (ör. kaç satır güncellendi)
        return $st->rowCount();
    }

    /** Verilen tabloya yeni satır ekler ve eklenen satırın id'sini döndürür. */
    protected function insert(string $table, array $data): int
    {
        // Sütun adları: ['title', 'price', ...]
        $cols = array_keys($data);
        // "INSERT INTO tablo (a, b) VALUES (:a, :b)" sorgusunu oluştur; değerler :ad yer tutucularıyla güvenli gönderilir
        $sql  = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $cols),
                        implode(', ', array_map(fn($c) => ":$c", $cols)));
        $this->execute($sql, $data);
        // Yeni eklenen satırın id'sini döndür
        return (int) $this->db->lastInsertId();
    }

    /** Sadece izin verilen sütunları günceller (beyaz liste). */
    protected function updateById(string $table, int $id, array $data, array $allowed): int
    {
        // Sadece izin verilen sütunları bırak (beyaz liste) - ör. kullanıcı user_id'yi değiştiremesin
        $data = array_intersect_key($data, array_flip($allowed));
        if (!$data) return 0;
        // "a = :a, b = :b" kısmını oluştur
        $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($data)));
        $data['id'] = $id;
        // Sadece bu id'li satırı güncelle
        return $this->execute("UPDATE $table SET $set WHERE id = :id", $data);
    }

    /**
     * İŞLEM (transaction): içindeki tüm sorgular ya birlikte başarılı olur
     * ya da hata olursa hepsi geri alınır (rollback).
     */
    public function transaction(callable $work)
    {
        // İşlemi başlat: bundan sonraki değişiklikler hemen kalıcı olmaz
        $this->db->beginTransaction();
        try {
            // Asıl işi yap (birden fazla sorgu olabilir)
            $result = $work();
            // Hepsi başarılı -> değişiklikleri birlikte kalıcı yap
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            // Bir hata oldu -> bütün değişiklikleri geri al (yarım kayıt kalmasın)
            $this->db->rollBack();
            // Hatayı yukarı ilet; kullanıcıya hata mesajı gösterilsin
            throw $e;
        }
    }
}
