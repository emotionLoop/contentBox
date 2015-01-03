{*
*  @author    Miguel Costa for emotionLoop
*  @copyright emotionLoop
*}

{extends file="helpers/form/form.tpl"}


{block name="label"}
    {if $input.type == 'topform'}
        <div id='topform-label' >
             <div class="col-md-3 row" style="background-color: transparent;">
                <div class="top-logo">
                    <img src="{$input.logoImg|escape:html}" alt="contentBox" style="float:left;">
                </div>
                <div class="col-md-8 top-module-description">
                    <h1 class="top-module-title">{$input.moduleName|escape:html}</h1>

                    <div class="top-module-sub-title">{$input.moduleDescription|escape:html}</div>

                    <div class="top-module-my-name"><a href="http://contentbox.org/?v={$input.moduleVersion|escape:html}">contentBox {$input.moduleVersion|escape:html}</a></div>
                    <div class="">by <a href="http://emotionloop.com/?contentbox">emotionLoop</a></div>
                </div>
            </div>
           
        </div>        
    {else}
        {$smarty.block.parent}
    {/if}
{/block}

{block name="input"}

    {if $input.type == 'topform'}

        <div class="row" style="background-color: transparent;" >
            <div class="col-md-4">
                <span><b>Shop:</b></span>
                <select id="contentbox_shop_select" name="contentbox_shop_select">
                    {foreach $input.shops as $shop}
                        <option id="id_{$shop['id_shop']|escape}" value="{$shop['id_shop']|escape}"
                            {if ( $input.current_shop_id == $shop['id_shop'] )}
                            selected
                            {/if}
                            >
                            {$shop['name']|escape}
                        </option>
                    {/foreach}
                </select>                
            </div>
            {if !$input.monolanguage }
            <div class="col-md-4">
                <span><b>Language:</b></span>
                <select id="contentbox_language_select" name="contentbox_language_select">
                    {foreach $input.languages as $language}
                        <option id="id_{$language['id_lang']|escape}" value="{$language['id_lang']|escape}"
                            {if ( $input.current_language_id == $language['id_lang'] )}
                            selected
                            {/if}
                            >
                            {$language['name']|escape}
                        </option>
                    {/foreach}
                </select>                
            </div>
            {/if}
            <div class="col-md-3">
                <div >&nbsp;</div>
                <input type="submit" value="Select" class="btn btn-primary">
                <input type="hidden" name="ignore_changes" id="ignore_changes" value="">
            </div>
        </div>
    {elseif $input.type == 'files_area' }
        <div>
            {if !empty( $input.files ) }
                {foreach $input.files as $pos=>$file}
                    {if $file['name'] != 'index.php'}
                        <div class="fileContainer" style="" rel="content/{$file['name']|escape}" data-filename="{$file['name']|escape}" data-filepath="{$input.path|escape}">
                            <div class="fileUrl">
                                <img src="{$input.path|escape}img/url.png" alt="URL"/>
                            </div>
                            <div class="fileDelete" >
                                <img src="{$input.path|escape}img/close.png" alt="DELETE"/>
                            </div>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    {if in_array( strtolower( $file['extension'] ), $input.imagesExtensions ) }
                                    <td style="height:90px;" valign="middle">
                                        <img src="{$input.path|escape}content/{$file['name']|escape:url}" style="max-width:100px; max-height:100px" />
                                    </td>
                                    {else}
                                    <td style="height:90px;" valign="middle">
                                        *.{$file['extension']|escape:url}
                                    </td>
                                    {/if}
                                </tr>
                            </table>
                            <div class="fileName">{$file['name']|escape:url}</div>
                        </div>
                        {if (($pos % 3) === 0 ) && ($pos != 0)}
                            <div class="mcClear"></div>
                        {/if}
                    {/if}
                {/foreach}
                <input type="hidden" name="delete_file" id="delete_file" value="">
                <div style="clear:both;"></div>
            {/if}
        </div>

    {else}
        {$smarty.block.parent}
    {/if}

{/block}