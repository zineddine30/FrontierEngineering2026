<!DOCTYPE html>
<html>
<head><title>Query Doctor — Benchmark Results</title></head>
<body>
<h1>Benchmark Results</h1>
<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>Endpoint</th><th>Query Count</th><th>Total Query Time (ms)</th>
        <th>Request Duration (ms)</th><th>Last Run</th>
    </tr>
    @foreach ($rows as $row)
    <tr>
        <td>{{ $row['endpoint'] }}</td>
        <td>{{ $row['query_count'] }}</td>
        <td>{{ $row['total_time_ms'] }}</td>
        <td>{{ $row['duration_ms'] }}</td>
        <td>{{ $row['timestamp'] }}</td>
    </tr>
    @endforeach
</table>
</body>
</html>