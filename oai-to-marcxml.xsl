<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns="http://www.loc.gov/MARC21/slim" xmlns:oai="http://www.openarchives.org/OAI/1.1/oai_marc" version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" exclude-result-prefixes="oai">
	<xsl:template match="oai_marc">
		<record xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.loc.gov/MARC21/slim
		http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd" >
			<leader>
				<xsl:text>     </xsl:text>
				<xsl:value-of select="@status"/>
				<xsl:value-of select="@type"/>
				<xsl:value-of select="@level"/>
				<xsl:text>  22     </xsl:text>
				<xsl:value-of select="@encLvl"/>
				<xsl:value-of select="@catForm"/>
				<xsl:text> 4500</xsl:text>
			</leader>
			<xsl:apply-templates select="fixfield|varfield"/>
		</record>
	</xsl:template>

	<xsl:template match="fixfield">
		<xsl:element name="controlfield">
			<xsl:call-template name="id2tag"/>
			<xsl:value-of select="text()"/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="varfield">
		<xsl:element name="datafield">
			<xsl:call-template name="id2tag"/>

			<xsl:attribute name="ind1">
				<xsl:call-template name="idBlankSpace">
					<xsl:with-param name="value" select="@i1"/>
				</xsl:call-template>
			</xsl:attribute>

			<xsl:attribute name="ind2">
				<xsl:call-template name="idBlankSpace">
					<xsl:with-param name="value" select="@i2"/>
				</xsl:call-template>
			</xsl:attribute>

			<xsl:apply-templates select="subfield"/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="subfield">
		<xsl:element name="subfield">
			<xsl:attribute name="code">
				<xsl:value-of select="@label"/>
			</xsl:attribute>
			<xsl:value-of select="text()"/>
		</xsl:element>
	</xsl:template>

	<xsl:template name="id2tag">
		<xsl:attribute name="tag">
			<xsl:variable name="tag" select="@id"/>
			<xsl:choose>
				<xsl:when test="string-length($tag)=1">
					<xsl:text>00</xsl:text>
					<xsl:value-of select="$tag"/>
				</xsl:when>
				<xsl:when test="string-length($tag)=2">
					<xsl:text>0</xsl:text>
					<xsl:value-of select="$tag"/>
				</xsl:when>
				<xsl:when test="string-length($tag)=3">
					<xsl:value-of select="$tag"/>
				</xsl:when>
			</xsl:choose>
		</xsl:attribute>
	</xsl:template>

	<xsl:template name="idBlankSpace">
		<xsl:param name="value"/>
		<xsl:choose>
			<xsl:when test="string-length($value)=0">
				<xsl:text> </xsl:text>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$value"/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

  <!-- Ignore other tags that might be present in the document -->
  <xsl:template match="text()" />
</xsl:stylesheet>