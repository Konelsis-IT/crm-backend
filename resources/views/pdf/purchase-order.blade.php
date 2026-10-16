{{--
    Siparis formu PDF'i (D-192): satin alma teklifiyle ayni standart gorunum,
    ek olarak teslimat karti (adres, kisi, donem, beklenen teslim, tasiyici,
    takip no). Ortak sablon pdf/partials/purchase-document; veri
    App\Filament\Exports\PurchaseDocumentPdf::orderData.
--}}
@include('pdf.partials.purchase-document')
