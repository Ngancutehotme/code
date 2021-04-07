<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Cache-Control" content="no-cache" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <script>

    </script>

    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>TransportMe admin Login</title>
    <link href="<?php echo base_url() ?>/assests/css/transportme.css" rel="stylesheet" type="text/css" />
    <!-- Alertify -->
    <script src="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css" />
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css" />
    <style type="text/css">
        .login_title.qr_code {
            margin-top: 99px !important;
            ;
        }

        .back {
            font-size: 13px;
            margin: 10px 60px 0px 60px !important;
            text-align: left;
            font-weight: normal;
        }

        a.back {
            color: #cf4b17;
        }
    </style>
    <?php
    $base =  base_url();
    ?>

    <script type="text/javascript" charset="utf-8" src="<?php echo $base ?>media/js/jquery.js"></script>

    <!-- Add fancyBox main JS and CSS files -->
    <script type="text/javascript" src="<?php echo base_url() ?>assests/source/jquery.fancybox.js?v=2.1.5"></script>
    <link rel="stylesheet" type="text/css" href="<?php echo base_url() ?>assests/source/jquery.fancybox.css?v=2.1.5" media="screen" />



    <script type="text/javascript">
        $(document).ready(function() {
            <?php if ($message) : ?>
                var data = '<?php echo $message; ?>';
                if (data) {
                    alertify.notify(data, 'error', 5);
                }
            <?php endif; ?>

            /*
             *  Simple image gallery. Uses default settings
             */

            $('.fancybox').fancybox();

            /*
             *  Different effects
             */

            // Change title type, overlay closing speed
            $(".fancybox-effects-a").fancybox({
                helpers: {
                    title: {
                        type: 'outside'
                    },
                    overlay: {
                        speedOut: 0
                    }
                }
            });

            // Disable opening and closing animations, change title type
            $(".fancybox-effects-b").fancybox({
                openEffect: 'none',
                closeEffect: 'none',

                helpers: {
                    title: {
                        type: 'over'
                    }
                }
            });

            // Set custom style, close if clicked, change title type and overlay color
            $(".fancybox-effects-c").fancybox({
                wrapCSS: 'fancybox-custom',
                closeClick: true,

                openEffect: 'none',

                helpers: {
                    title: {
                        type: 'inside'
                    },
                    overlay: {
                        css: {
                            'background': 'rgba(238,238,238,0.85)'
                        }
                    }
                }
            });

            // Remove padding, set opening and closing animations, close if clicked and disable overlay
            $(".fancybox-effects-d").fancybox({
                padding: 0,

                openEffect: 'elastic',
                openSpeed: 150,

                closeEffect: 'elastic',
                closeSpeed: 150,

                closeClick: true,

                helpers: {
                    overlay: null
                }
            });
        });
    </script>
    <script src="<?php echo base_url(); ?>assests/js/gen_validatorv4.js" type="text/javascript"></script>
</head>

<body>
    <div class="login-container">
        <div class="login-content">
            <h1 class="login_title <?php echo (isset($url) ? 'qr_code' : '') ?>">Administrator Login</h1>
            <div style="color:#000;align:left;font-size:12px;line-height:10px;margin-left:40px;">
                <p>Enter code from 2FA app:
                    <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=vi&gl=US" style="color: blue;" target="_blank">Android</a>
                    <span> or </span>
                    <a href="https://apps.apple.com/us/app/google-authenticator/id388497605" style="color: blue;" target="_blank">iOS</a>
                </p>
                <?php echo validation_errors(); ?>
            </div>
            <form id="frm1" action="<?php echo base_url() ?>index.php/admin/check_google_authenticator" method="post">
                <?php if (!isset($status) || $status != true) : ?>
                    <img style="margin-left: 90px; width:180px" type="url" name="url" src="<?php echo $url ?>" />
                <?php endif; ?>
                <input type="hidden" id="secret" name="secret" value="<?php echo (isset($secret) ? $secret : ''); ?>">
                <input type="hidden" id="remember_me" name="remember_me" value="<?php echo (isset($remember_me) ? $remember_me : 30); ?>">
                <input type="hidden" id="qr_code_url" name="qr_code_url" value="<?php echo (isset($url) ? $url : ''); ?>">
                <input name="code" type="number" class="user_field" value="" />
                <input type="submit" class="bt_login" name="Login" value="Login">
            </form>
            <a href="<?php echo base_url() ?>admin" class="back">
                << Back</a>

                    <!-- end .login -->
        </div>

        <?php include 'footer.php'; ?>