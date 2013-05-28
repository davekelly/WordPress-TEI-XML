<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
    xmlns:tei="http://www.tei-c.org/ns/1.0">


    <xsl:template match="/">
        


                <div id="tei_content">
                    <xsl:apply-templates select="//tei:fileDesc" mode="doctitle"/>
                    <xsl:apply-templates select="//tei:sourceDesc" mode="doctitle"/>

                    <xsl:apply-templates/>
                </div>

                <xsl:if test="count(//tei:note) > 0">
                    <div id="footnotes">
                        <h3>Notes</h3>
                        <xsl:apply-templates select="//tei:note[ancestor::tei:p]" mode="endlist"/>
                    </div>
                    <!-- end endnotes div -->
                </xsl:if>

                <div id="headnote">
                    <xsl:apply-templates select="//tei:notesStmt" mode="headnote"/>
                </div>

                <div id="footer">
                    <h4>Copyright Notice</h4>
                    <p><a rel="license" href="http://creativecommons.org/licenses/by-nc-nd/3.0/">
                            <img alt="Creative Commons License" style="border-width:0"
                                src="http://i.creativecommons.org/l/by-nc-nd/3.0/88x31.png"/>
                        </a><br/>This <span xmlns:dc="http://purl.org/dc/elements/1.1/"
                            href="http://purl.org/dc/dcmitype/Text" rel="dc:type">work</span> by <a
                            xmlns:cc="http://creativecommons.org/ns#"
                            href="../../About/TMHA_1.0_About_Copyright.html"
                            property="cc:attributionName" rel="cc:attributionURL">Thomas Moore
                            Hypermedia Archive</a> is licensed under a <a rel="license"
                            href="http://creativecommons.org/licenses/by-nc-nd/3.0/">Creative
                            Commons Attribution-Non-Commercial-No Derivative Works 3.0 Unported
                            License</a>.</p>
                </div>
            <!-- 
            </body>
        </html> -->
    </xsl:template>

    <xsl:template match="tei:profileDesc"/>
    <xsl:template match="tei:encodingDesc"/>
    <xsl:template match="tei:revisionDesc"/>

    <xsl:template match="tei:fileDesc" mode="doctitle">
        <h2>
            <xsl:value-of select="tei:titleStmt/tei:title[@type='short']"/>, by <xsl:value-of
                select="tei:titleStmt/tei:author"/>
        </h2>
    </xsl:template>

    <xsl:template match="tei:sourceDesc" mode="doctitle">
        <h3>[<span class="mono">
                <xsl:value-of select="tei:biblStruct/tei:monogr/tei:title"/>
            </span>, vol. <xsl:value-of select="//tei:imprint/tei:biblScope[@type='vol']"/>,
                <xsl:value-of select="//tei:imprint/tei:date"/>, pp. <xsl:value-of
                select="//tei:imprint/tei:biblScope[@type='pp']"/>]</h3>
        <h3>
            <xsl:value-of select="tei:biblStruct/tei:analytic/tei:title"/>
        </h3>

    </xsl:template>


    <xsl:template match="tei:encodingDesc" mode="editdecl">
        <p class="editdecl">
            <xsl:value-of select="tei:editorialDecl/tei:segmentation/tei:p"/>
        </p>
        <p class="editdecl">
            <xsl:value-of select="tei:editorialDecl/tei:interpretation/tei:p"/>
        </p>
    </xsl:template>

    <xsl:template match="tei:teiHeader"/>
    <xsl:template match="tei:front"/>

    <xsl:template match="tei:body">
        <xsl:for-each select="tei:p">

            <p class="para">

                <xsl:value-of select="tei:p"/>
                <xsl:apply-templates/>
            </p>
        </xsl:for-each>

    </xsl:template>

    <xsl:template match="tei:persName[@ref]">
        <a href="TMHA_1.0_Prose_EdRev_Glossary_Persons.html{@ref}">
            <xsl:value-of select="descendant-or-self::node()"/>
        </a>
    </xsl:template>

    <xsl:template match="tei:cit">
        <xsl:choose>
            <xsl:when test="tei:quote[@type='block']">
                <xsl:apply-templates select="tei:quote[@type='block']"/>
                <span class="citref">(<xsl:value-of select="tei:bibl"/>)</span>
            </xsl:when>
            <xsl:when test="tei:quote[@type='inline']">
                <xsl:apply-templates select="tei:quote[@type='inline']"/>
                <span class="inlinecitref">(<xsl:value-of select="tei:bibl"/>)</span>
            </xsl:when>
        </xsl:choose>

    </xsl:template>

    <xsl:template match="tei:quote[@type='block']">
        <span class="blockquote">
            <xsl:value-of select="tei:quote[@type='block']"/>
            <xsl:apply-templates/>
        </span>
    </xsl:template>

    <xsl:template match="tei:quote[@type='block']/tei:p">
        <span class="pquote">
            <xsl:value-of select="tei:quote[@type='block']/tei:p"/>
            <xsl:apply-templates/>
        </span>
    </xsl:template>

    <xsl:template match="tei:quote[@type='inline']"> "<span class="inlinequote">
            <xsl:value-of select="tei:quote[@type='inline']"/>
            <xsl:apply-templates/>
        </span>" </xsl:template>

    <xsl:template match="tei:l">
        <span class="poetry">
            <xsl:value-of select="tei:l"/>
            <xsl:apply-templates/>
        </span>
    </xsl:template>

    <xsl:template match="tei:emph">
        <span class="emph">
            <xsl:value-of select="tei:emph"/>
            <xsl:apply-templates/>
        </span>
    </xsl:template>

    <xsl:template match="tei:foreign">
        <span class="foreign">
            <xsl:value-of select="tei:foreign"/>
            <xsl:apply-templates/>
        </span>
    </xsl:template>

    <xsl:template match="tei:hi[@rend='italic']">
        <span class="emph">
            <xsl:value-of select="tei:hi"/>
            <xsl:apply-templates/>
        </span>
    </xsl:template>

    <xsl:template match="tei:q"> &quot;<xsl:value-of select="tei:q"
        /><xsl:apply-templates/>&quot; </xsl:template>

    <xsl:template match="tei:said"> &quot;<xsl:value-of select="tei:said"
        /><xsl:apply-templates/>&quot;</xsl:template>

    <xsl:template match="tei:pb">
        <span class="pb"> [Page <xsl:value-of select="@n"/>] </span>
    </xsl:template>

    <xsl:template match="tei:choice">
        <xsl:if test="descendant::tei:sic">
            <span class="sic"><xsl:value-of select="tei:sic"/> [sic; <xsl:value-of select="tei:corr"
                />] </span>

        </xsl:if>
        <xsl:if test="descendant::tei:orig">
            <span class="orig">
                <xsl:value-of select="tei:orig"/>* </span>

        </xsl:if>
    </xsl:template>


    <xsl:template match="tei:sp">
        <xsl:choose>
            <xsl:when test="tei:speaker">
                <span class="speaker">[<xsl:value-of select="tei:speaker"/>]</span>
                <xsl:apply-templates/>
            </xsl:when>
            <xsl:otherwise>
                <xsl:apply-templates/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="tei:title">
        <xsl:choose>
            <xsl:when test="@level='m' or @level='j'">
                <span class="mono">
                    <xsl:value-of select="."/>
                </span>
                <xsl:text/>
            </xsl:when>

            <xsl:when test="@level='a'"> "<xsl:value-of select="."/>" <xsl:text/>
            </xsl:when>
        </xsl:choose>
    </xsl:template>

    <!-- Template for footnote references, inline with the text.
        Sets up cross-references to footnote text that appears after
        the document. -->
    <xsl:template match="tei:note">
        <xsl:variable name="inc">
            <xsl:number level="any" count="tei:note"/>
        </xsl:variable>
        <a href="#fn{$inc}" name="fnref{$inc}">
            <sup>
                <xsl:value-of select="@n"/>
            </sup>

        </a>
    </xsl:template>


    <!-- Template for footnote text that should appear following
        the document, with cross references back to where the footnote
        originally appears. -->
    <xsl:template match="tei:note" mode="endlist">
        <xsl:variable name="incr">
            <xsl:number level="any" count="tei:note"/>
        </xsl:variable>
        <p>
            <a href="#fnref{$incr}" name="fn{$incr}" title="Return to text">
                <sup>
                    <xsl:value-of select="@n"/>
                </sup>
            </a>
            <xsl:choose>
                <xsl:when test="@resp='#TM'"> [Moore's note] <xsl:value-of select="."/>
                </xsl:when>
                <xsl:when test="@resp='#FB'"> [editor's note] <xsl:value-of select="."/>
                </xsl:when>
            </xsl:choose>
            <xsl:text/>
        </p>
    </xsl:template>

    <xsl:template match="tei:notesStmt" mode="headnote">
<h4>Editor's note</h4>
        <xsl:for-each select="tei:note/tei:p">
            <p class="headnotetext">
                <xsl:value-of select="tei:p"/>
                <xsl:apply-templates/>
            </p>
        </xsl:for-each>

    </xsl:template>



</xsl:stylesheet>
