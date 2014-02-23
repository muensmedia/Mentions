<?php
/*
    This file is part of Mentions.

    Mentions is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Mentions is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Mentions.  If not, see <http://www.gnu.org/licenses/>.
*/

class MentionsPlugin extends MantisPlugin
{
    function register()
    {
        $this->name         = 'Mentions';
        $this->description  = 'Enables @Mentions in BugNotes';
        $this->page         = '';
        $this->version      = '0.1';
        $this->requires     = array('MantisCore' => '1.2.0', 'jQuery' => '1.6');

        $this->author       = 'Malte Müns | münsmedia.de';
        $this->contact      = 'm.muens-at-muensmedia.de';
        $this->url          = 'http://muensmedia.de';
    }

    function hooks()
    {
        return array(
            'EVENT_LAYOUT_RESOURCES' => 'resources',
            'EVENT_BUGNOTE_ADD' => 'bugnote_add',
            // 'EVENT_BUGNOTE_ADD_FORM' => 'form_add'  // not working :(
            'EVENT_DISPLAY_TEXT' => 'text', # Text String Display
            'EVENT_DISPLAY_FORMATTED' => 'formatted', # Formatted String Display
            'EVENT_DISPLAY_RSS' => 'rss', # RSS String Display
            'EVENT_DISPLAY_EMAIL' => 'email', # Email String Display
        );
    }

    function resources($p_event)
    {
        if (auth_is_user_authenticated()) {
            $t_users = array();
            $p_project_id = helper_get_current_project();

            $t_users = project_get_all_user_rows($p_project_id);
            $json = "";
            foreach ($t_users as $t_user) {
                $json .= "{ id:" . $t_user['id'] . ", name:'" . $t_user['realname'] . "', 'avatar':'https://www.gravatar.com/avatar/" . md5(user_get_field($t_user['id'], 'email')) . "?s=96', 'type':'user' },";
            }
            return '<link rel="Stylesheet" type="text/css" href="./plugins/Mentions/files/jquery.mentionsInput.css" />' .
            ' <script src="./plugins/Mentions/files/lib/underscore-min.js" type="text/javascript"></script>' .
            "<script src='./plugins/Mentions/files/lib/jquery.events.input.js' type='text/javascript'></script>
              <script src='./plugins/Mentions/files/lib/jquery.elastic.js' type='text/javascript'></script>
              <script src='./plugins/Mentions/files/jquery.mentionsInput.js' type='text/javascript'></script>" .
            "<script type='text/javascript'>
            (function($){
                $(document).ready(function() {
                    if(window.location.pathname.substring(window.location.pathname.lastIndexOf('/')+1, window.location.pathname.length) == 'view.php'){
                        $(\"textarea[name='bugnote_text']\").parent().prepend('" . $this->form_add() . "');
                        $(\"textarea[name='bugnote_text']\").hide();

                        $(\".mention\").mentionsInput({
                          onDataRequest:function (mode, query, callback) {
                                var data = [
                              " . $json . "
                            ];

                            data = _.filter(data, function(item) { return item.name.toLowerCase().indexOf(query.toLowerCase()) > -1 });

                            callback.call(this, data);
                          }
                        });
                        $(\"form[name='bugnoteadd'] input[type='submit']\").focus(function(e){
                            $(\".mention\").mentionsInput('val', function(text) {
                                 $(\"textarea[name='bugnote_text']\").val(text);
                            });
                        });
                        $(\"form[name='bugnoteadd'] input[type='submit']\").mousemove(function(e){
                            $(\".mention\").mentionsInput('val', function(text) {
                                 $(\"textarea[name='bugnote_text']\").val(text);
                            });
                        });
                    }
                });
            })(jQuery);
            </script>";
        }
    }

    function form_add()
    {
        return "<textarea class=\"mention\" placeholder=\"\" style=\"height: 150px;\"></textarea>";
    }

    function bugnote_add($event, $bug_id, $bugnote_id)
    {
        $bug_data = bug_get($bug_id, true);

        // Get text
        $bugnote_text = bugnote_get_text($bugnote_id);

        $pattern = '#\@\[([a-zA-Z\.\ \-ÄäÖöÜüÀÁáÂâÈèÉéÊêÙùÚúßÇç]*)\]\(user:([0-9]*)\)#is';
        $result = preg_match_all($pattern, $bugnote_text, $subpattern, PREG_SET_ORDER);

        if ($result) {
            foreach ($subpattern as $user) {
                var_dump($user);
                $user_id = $user[2];

                $message_id = 'email_notification_title_for_action_bugnote_submitted';

                lang_push(user_pref_get_language($user_id, $bug_data->project_id));
                $visible_bug_data = email_build_visible_bug_data($user_id, $bug_id, $message_id);
                email_bug_info_to_one_user($visible_bug_data, $message_id, $bug_data->project_id, $user_id);
                lang_pop();

                // log it
                //history_log_event_special( $bug_id, 1, '|'.$user_id.'|');
            }

            email_send_all();
        }
    }

    /**
     * Plain text processing.
     * @param string Event name
     * @param string Unformatted text
     * @param boolean Multiline text
     * @return multi array with formatted text and multiline paramater
     */
    function text($p_event, $p_string, $p_multiline = true)
    {
        return $this->replaceMentionCode($p_string);
    }

    /**
     * Formatted text processing.
     * @param string Event name
     * @param string Unformatted text
     * @param boolean Multiline text
     * @return multi array with formatted text and multiline paramater
     */
    function formatted($p_event, $p_string, $p_multiline = true)
    {
        return $this->replaceMentionCode($p_string);
    }

    /**
     * RSS text processing.
     * @param string Event name
     * @param string Unformatted text
     * @return string Formatted text
     */
    function rss($p_event, $p_string)
    {
        return $this->replaceMentionCode($p_string);
    }

    /**
     * Email text processing.
     * @param string Event name
     * @param string Unformatted text
     * @return string Formatted text
     */
    function email($p_event, $p_string)
    {
        return $this->replaceMentionCode($p_string);
    }

    function replaceMentionCode($p_string)
    {
        $p_string = preg_replace_callback('#\@\[([a-zA-Z\.\ \-ÄäÖöÜüÀÁáÂâÈèÉéÊêÙùÚúßÇç]*)\]\(user:([0-9]*)\)#is', function ($matches) {
            $user = user_get_field($matches[2], 'realname');
            return '@' . $user . ': ';
        }, $p_string);

        return $p_string;
    }
}

?>