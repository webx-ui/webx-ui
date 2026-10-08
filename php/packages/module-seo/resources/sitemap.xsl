<?xml version="1.0" encoding="UTF-8"?>
<!--
  What a browser shows for /sitemap.xml and its files: the map's own text, laid out as the file
  is, with the addresses clickable. Not a table on purpose: whoever opens a sitemap is checking
  the markup a crawler reads, and a table would show them something else.

  Crawlers do not run stylesheets, so nothing here changes what the map says.
-->
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
  xmlns:x="http://www.w3.org/1999/xhtml"
  exclude-result-prefixes="s x">

  <xsl:output method="html" encoding="UTF-8" indent="no" doctype-system="about:legacy-compat"/>
  <xsl:strip-space elements="*"/>

  <xsl:template match="/">
    <html lang="en">
      <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <meta name="robots" content="noindex"/>
        <title>Sitemap</title>
        <style>
          :root {
            color-scheme: light dark;
            --bg: #ffffff; --bar: #f6f8fa; --line: #d1d9e0; --text: #1f2328; --muted: #59636e;
            --tag: #116329; --attr: #0550ae; --value: #0a3069; --date: #953800; --hover: #f6f8fa;
          }
          @media (prefers-color-scheme: dark) {
            :root {
              --bg: #0d1117; --bar: #151b23; --line: #3d444d; --text: #e6edf3; --muted: #9198a1;
              --tag: #7ee787; --attr: #79c0ff; --value: #a5d6ff; --date: #ffa657; --hover: #151b23;
            }
          }
          * { box-sizing: border-box; }
          body { margin: 0; background: var(--bg); color: var(--text);
            font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
          header { position: sticky; top: 0; display: flex; flex-wrap: wrap; gap: 4px 16px;
            align-items: baseline; padding: 10px 16px; background: var(--bar);
            border-bottom: 1px solid var(--line); }
          header strong { font-weight: 600; }
          header span { color: var(--muted); }
          header a { color: var(--attr); text-decoration: none; }
          header a:hover { text-decoration: underline; }
          pre { margin: 0; padding: 12px 16px 32px; overflow-x: auto;
            font: 13px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
          .b { display: block; margin: 0 -16px; padding: 0 16px; }
          .b:hover { background: var(--hover); }
          .t { color: var(--tag); }
          .a { color: var(--attr); }
          .v, .v a { color: var(--value); }
          .d { color: var(--date); }
          .m { color: var(--muted); }
          a { color: inherit; text-decoration: none; }
          a:hover { text-decoration: underline; }
        </style>
      </head>
      <body>
        <header>
          <strong>Sitemap</strong>
          <xsl:choose>
            <xsl:when test="s:sitemapindex">
              <xsl:call-template name="count">
                <xsl:with-param name="n" select="count(s:sitemapindex/s:sitemap)"/>
                <xsl:with-param name="one" select="'file'"/>
                <xsl:with-param name="many" select="'files'"/>
              </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
              <xsl:call-template name="count">
                <xsl:with-param name="n" select="count(s:urlset/s:url)"/>
                <xsl:with-param name="one" select="'address'"/>
                <xsl:with-param name="many" select="'addresses'"/>
              </xsl:call-template>
              <xsl:if test="s:urlset/s:url/x:link">
                <xsl:call-template name="count">
                  <xsl:with-param name="n" select="count(s:urlset/s:url/x:link)"/>
                  <xsl:with-param name="one" select="'alternate'"/>
                  <xsl:with-param name="many" select="'alternates'"/>
                </xsl:call-template>
              </xsl:if>
              <a href="/sitemap.xml">All files</a>
            </xsl:otherwise>
          </xsl:choose>
        </header>
        <pre>
          <span class="m">&lt;?xml version="1.0" encoding="UTF-8"?&gt;</span>
          <xsl:text>&#10;</xsl:text>
          <xsl:for-each select="processing-instruction('xml-stylesheet')">
            <span class="m">&lt;?xml-stylesheet <xsl:value-of select="."/>?&gt;</span>
            <xsl:text>&#10;</xsl:text>
          </xsl:for-each>
          <xsl:apply-templates select="*"/>
        </pre>
      </body>
    </html>
  </xsl:template>

  <xsl:template match="s:urlset">
    <span class="t">&lt;urlset</span>
    <xsl:call-template name="xmlns"/>
    <xsl:if test="s:url/x:link">
      <xsl:text>&#10;  </xsl:text>
      <span class="a">xmlns:xhtml</span>=<span class="v">"http://www.w3.org/1999/xhtml"</span>
    </xsl:if>
    <span class="t">&gt;</span>
    <xsl:apply-templates select="s:url"/>
    <span class="t">&lt;/urlset&gt;</span>
  </xsl:template>

  <xsl:template match="s:sitemapindex">
    <span class="t">&lt;sitemapindex</span>
    <xsl:call-template name="xmlns"/>
    <span class="t">&gt;</span>
    <xsl:apply-templates select="s:sitemap"/>
    <span class="t">&lt;/sitemapindex&gt;</span>
  </xsl:template>

  <!-- A block per entry, so a pointer over it shows where one ends and the next begins. -->
  <xsl:template match="s:url | s:sitemap">
    <span class="b">
      <xsl:text>  </xsl:text>
      <span class="t">&lt;<xsl:value-of select="local-name()"/>&gt;</span>
      <xsl:apply-templates select="s:loc | s:lastmod | x:link"/>
      <xsl:text>&#10;  </xsl:text>
      <span class="t">&lt;/<xsl:value-of select="local-name()"/>&gt;</span>
    </span>
  </xsl:template>

  <xsl:template match="s:loc">
    <xsl:text>&#10;    </xsl:text>
    <span class="t">&lt;loc&gt;</span>
    <span class="v"><a href="{normalize-space(.)}"><xsl:value-of select="normalize-space(.)"/></a></span>
    <span class="t">&lt;/loc&gt;</span>
  </xsl:template>

  <xsl:template match="s:lastmod">
    <xsl:text>&#10;    </xsl:text>
    <span class="t">&lt;lastmod&gt;</span>
    <span class="d"><xsl:value-of select="normalize-space(.)"/></span>
    <span class="t">&lt;/lastmod&gt;</span>
  </xsl:template>

  <xsl:template match="x:link">
    <xsl:text>&#10;    </xsl:text>
    <span class="t">&lt;xhtml:link</span>
    <xsl:call-template name="attribute"><xsl:with-param name="name" select="'rel'"/></xsl:call-template>
    <xsl:call-template name="attribute"><xsl:with-param name="name" select="'hreflang'"/></xsl:call-template>
    <xsl:text>&#10;               </xsl:text>
    <span class="a">href</span>=<span class="v">"<a href="{@href}"><xsl:value-of select="@href"/></a>"</span>
    <span class="t">/&gt;</span>
  </xsl:template>

  <xsl:template name="attribute">
    <xsl:param name="name"/>
    <xsl:text>&#10;               </xsl:text>
    <span class="a"><xsl:value-of select="$name"/></span>=<span class="v">"<xsl:value-of select="@*[local-name() = $name]"/>"</span>
  </xsl:template>

  <xsl:template name="count">
    <xsl:param name="n"/>
    <xsl:param name="one"/>
    <xsl:param name="many"/>
    <span>
      <xsl:value-of select="$n"/>
      <xsl:text> </xsl:text>
      <xsl:choose>
        <xsl:when test="$n = 1"><xsl:value-of select="$one"/></xsl:when>
        <xsl:otherwise><xsl:value-of select="$many"/></xsl:otherwise>
      </xsl:choose>
    </span>
  </xsl:template>

  <xsl:template name="xmlns">
    <xsl:text> </xsl:text>
    <span class="a">xmlns</span>=<span class="v">"http://www.sitemaps.org/schemas/sitemap/0.9"</span>
  </xsl:template>
</xsl:stylesheet>
