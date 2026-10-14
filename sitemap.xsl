<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
  <xsl:output method="html" encoding="UTF-8" indent="yes"/>
  <xsl:template match="/">
    <html lang="id">
      <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title>XML Sitemap — Ruethies Patisserie</title>
        <style>
          body{font-family:Georgia,'Times New Roman',serif;background:#FAF6F0;color:#3a2b2b;margin:0;padding:48px 24px}
          .wrap{max-width:860px;margin:0 auto}
          h1{font-size:34px;margin:0 0 8px;color:#4a0d0d}
          p{line-height:1.6;color:#6b5a5a}
          a{color:#7A0000}
          table{width:100%;border-collapse:collapse;margin-top:24px;background:#fff;border:1px solid #e5d9d0}
          th{text-align:left;padding:12px 16px;border-bottom:2px solid #7A0000;color:#4a0d0d;font-size:14px}
          td{padding:12px 16px;border-bottom:1px solid #f0e6de;font-size:14px;word-break:break-all}
          tr:nth-child(even) td{background:#faf5f0}
          .count{margin-top:16px;font-size:14px;color:#6b5a5a}
        </style>
      </head>
      <body>
        <div class="wrap">
          <h1>XML Sitemap</h1>
          <p>Ini adalah XML Sitemap <strong>Ruethies Patisserie</strong> (<a href="https://ruethiespatisserie.com/">ruethiespatisserie.com</a>), ditujukan untuk dibaca mesin pencari seperti Google. Info lebih lanjut: <a href="https://www.sitemaps.org/">sitemaps.org</a>.</p>
          <table>
            <tr><th>URL</th><th>Terakhir diubah</th><th>Frekuensi</th><th>Prioritas</th></tr>
            <xsl:for-each select="s:urlset/s:url">
              <tr>
                <td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
                <td><xsl:value-of select="s:lastmod"/></td>
                <td><xsl:value-of select="s:changefreq"/></td>
                <td><xsl:value-of select="s:priority"/></td>
              </tr>
            </xsl:for-each>
          </table>
          <p class="count">Total <xsl:value-of select="count(s:urlset/s:url)"/> URL.</p>
        </div>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
