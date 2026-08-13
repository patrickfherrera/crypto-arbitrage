<x-mail::message>
# Arbitrage logs export

Your CSV export is attached ({{ number_format($rowCount) }} rows, {{ $rangeLabel }}).

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
