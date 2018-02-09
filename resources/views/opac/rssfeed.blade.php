<?xml version="1.0" ?>
<rss version="2.0">
  <channel>
    <title>{{ $feed['title'] }}</title>
    <link>{{ $feed['uri'] }}</link>
    <description>{{ $feed['description'] }}</description>
    @foreach ($records as $item)
      <item>
        <title>{{ $item->title }}</title>
        <link>{{ $item->link }}</link>
        <description>{{ $item->description }}</description>
      </item>
    @endforeach  
  </channel>
</rss>
