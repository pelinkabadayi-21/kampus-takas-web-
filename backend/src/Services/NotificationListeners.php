<?php
/**
 * Bildirim kancaları: mesaj ve teklif olaylarını dinleyip ilgili kişiye bildirim yazar.
 * index.php (ve testler) uygulama başlarken register() çağırır.
 */
class NotificationListeners
{
    public static function register(NotificationRepository $n): void
    {
        Events::on('message.sent', function (array $e) use ($n) {
            $n->create($e['receiver_id'], 'mesaj',
                "{$e['sender_name']}: \"{$e['listing_title']}\" hakkında yeni mesaj",
                "mesajlar.html?ilan={$e['listing_id']}&kisi={$e['sender_id']}");
        });

        Events::on('offer.created', function (array $e) use ($n) {
            $n->create($e['owner_id'], 'teklif',
                "{$e['offerer_name']}, \"{$e['listing_title']}\" ilanınıza takas teklifi verdi",
                'teklifler.html?sekme=gelen');
        });

        Events::on('offer.accepted', function (array $e) use ($n) {
            $n->create($e['offerer_id'], 'teklif_sonuc',
                "🎉 \"{$e['listing_title']}\" için teklifiniz kabul edildi. Buluşma: " . ($e['meeting_point'] ?: 'mesajlaşarak belirleyin'),
                'teklifler.html?sekme=giden');
            foreach ($e['auto_closed'] as $c) {
                $n->create($c['offerer_id'], 'teklif_sonuc',
                    "\"{$c['listing_title']}\" için teklifiniz kapandı (ilan başka bir teklifle rezerve edildi)",
                    'teklifler.html?sekme=giden');
            }
        });

        Events::on('offer.rejected', function (array $e) use ($n) {
            $n->create($e['offerer_id'], 'teklif_sonuc',
                "\"{$e['listing_title']}\" için teklifiniz reddedildi", 'teklifler.html?sekme=giden');
        });

        Events::on('offer.cancelled', function (array $e) use ($n) {
            $n->create($e['owner_id'], 'teklif_sonuc',
                "\"{$e['listing_title']}\" ilanınıza gelen bir teklif geri çekildi", 'teklifler.html?sekme=gelen');
        });

        Events::on('offer.withdrawn', function (array $e) use ($n) {
            $n->create($e['other_id'], 'teklif_sonuc',
                "\"{$e['listing_title']}\" takası karşı taraf tarafından iptal edildi; ilan tekrar yayında",
                'teklifler.html');
        });

        Events::on('offer.completed', function (array $e) use ($n) {
            $n->create($e['other_id'], 'degerlendirme',
                "✅ \"{$e['listing_title']}\" takası tamamlandı. Karşı tarafı değerlendirmeyi unutmayın!",
                'teklifler.html');
        });
    }
}
