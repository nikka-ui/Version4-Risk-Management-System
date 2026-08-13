@php
  $level = $t['riskLevel'] ?? 'low';
  $isOverdue = (bool) ($t['isOverdue'] ?? false);
  $tone = $isOverdue ? 'bad' : 'ok';
@endphp
<tr{{ $level === 'critical' ? ' class="row--critical"' : '' }}>
  <td class="mono nowrap"><a href="/executive/tickets/{{ urlencode($t['reference']) }}">{{ $t['reference'] }}</a></td>
  <td class="sup-truncate">{{ $t['title'] ?? '—' }}</td>
  <td class="nowrap"><span class="risk-badge risk-badge--{{ $level }}">{{ $t['riskLevelLabel'] ?? ucfirst($level) }}</span></td>
  <td class="nowrap">{{ $t['categoryLabel'] ?? '—' }}</td>
  <td class="nowrap">{{ $t['department'] ?? '—' }}</td>
  <td><span class="pill pill--{{ $tone }}">{{ $t['statusLabel'] ?? '—' }}</span></td>
  <td class="nowrap">{{ !empty($t['updatedAt']) ? \Illuminate\Support\Carbon::parse($t['updatedAt'])->format('Y-m-d H:i') : '—' }}</td>
</tr>
