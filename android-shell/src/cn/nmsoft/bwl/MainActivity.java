package cn.nmsoft.bwl;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.os.Message;
import android.view.Gravity;
import android.view.View;
import android.view.WindowInsets;
import android.webkit.CookieManager;
import android.webkit.PermissionRequest;
import android.webkit.SslErrorHandler;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.PopupMenu;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/** Remote website shell: no bundled website, JavaScript bridge, or embedded credentials. */
public class MainActivity extends Activity {
    private static final String HOME = "https://memo.example.com/";
    private static final int FILE_PICKER = 1, MICROPHONE = 2, STORAGE = 3;
    private WebView web;
    private FrameLayout content;
    private LinearLayout error;
    private TextView errorMessage;
    private ProgressBar progress;
    private ValueCallback<Uri[]> fileCallback;
    private PermissionRequest microphoneRequest;
    private Runnable pendingDownload;
    private View fullScreen;
    private WebChromeClient.CustomViewCallback fullScreenCallback;
    private String retryUrl = HOME;

    @Override public void onCreate(Bundle saved) {
        super.onCreate(saved);
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(Color.WHITE);
        setContentView(root);
        if (Build.VERSION.SDK_INT >= 30) {
            getWindow().setDecorFitsSystemWindows(false);
            root.setOnApplyWindowInsetsListener((v, insets) -> {
                android.graphics.Insets bars = insets.getInsets(WindowInsets.Type.systemBars()
                        | WindowInsets.Type.displayCutout() | WindowInsets.Type.ime());
                v.setPadding(bars.left, bars.top, bars.right, bars.bottom);
                return WindowInsets.CONSUMED;
            });
        }
        LinearLayout bar = new LinearLayout(this);
        bar.setGravity(Gravity.CENTER_VERTICAL);
        TextView title = new TextView(this);
        title.setText("牛马备忘录");
        title.setTextSize(16);
        title.setTextColor(Color.rgb(31, 35, 41));
        title.setPadding(dp(16), 0, 0, 0);
        bar.addView(title, new LinearLayout.LayoutParams(0, dp(48), 1));
        title.setGravity(Gravity.CENTER_VERTICAL);
        Button menu = new Button(this, null, android.R.attr.borderlessButtonStyle);
        menu.setText("更多");
        menu.setContentDescription("更多操作");
        menu.setOnClickListener(v -> showMenu(menu));
        bar.addView(menu, new LinearLayout.LayoutParams(dp(64), dp(48)));
        root.addView(bar);
        progress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        root.addView(progress, new LinearLayout.LayoutParams(-1, dp(2)));
        content = new FrameLayout(this);
        root.addView(content, new LinearLayout.LayoutParams(-1, 0, 1));
        web = new WebView(this);
        content.addView(web, new FrameLayout.LayoutParams(-1, -1));
        setupError();
        WebSettings settings = web.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(true); // System document picker grants per-file access.
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        settings.setSupportMultipleWindows(true);
        settings.setMediaPlaybackRequiresUserGesture(true);
        settings.setUserAgentString(settings.getUserAgentString() + " NiumaMemo/1.0.0");
        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(web, false);
        web.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                if (!request.isForMainFrame()) return false;
                return route(request.getUrl());
            }
            @Override public void onPageStarted(WebView view, String url, android.graphics.Bitmap icon) {
                if (trusted(Uri.parse(url))) retryUrl = url;
                error.setVisibility(View.GONE);
                progress.setVisibility(View.VISIBLE);
            }
            @Override public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError failure) {
                if (request.isForMainFrame()) showError("暂时无法连接，请检查网络后重试。");
            }
            @Override public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse response) {
                if (request.isForMainFrame()) showError("网站暂时不可用（" + response.getStatusCode() + "），请稍后重试。");
            }
            @Override public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError failure) {
                handler.cancel();
                showError("安全连接验证失败，请检查手机时间或稍后重试。");
            }
            @Override public void onPageFinished(WebView view, String url) {
                progress.setVisibility(View.INVISIBLE);
                CookieManager.getInstance().flush();
            }
        });
        web.setWebChromeClient(new WebChromeClient() {
            @Override public void onProgressChanged(WebView view, int value) {
                progress.setProgress(value);
                if (value == 100) progress.setVisibility(View.INVISIBLE);
            }
            @Override public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (!trusted(Uri.parse(view.getUrl() == null ? "" : view.getUrl()))) return false;
                if (fileCallback != null) fileCallback.onReceiveValue(null);
                fileCallback = callback;
                try {
                    startActivityForResult(params.createIntent(), FILE_PICKER);
                } catch (ActivityNotFoundException e) {
                    fileCallback.onReceiveValue(null);
                    fileCallback = null;
                    toast("没有可用的文件选择器，请安装系统文件管理器。");
                }
                return true;
            }
            @Override public void onPermissionRequest(PermissionRequest request) {
                if (!trusted(request.getOrigin()) || !hasAudio(request)) { request.deny(); return; }
                if (microphoneRequest != null) microphoneRequest.deny();
                microphoneRequest = request;
                if (checkSelfPermission(Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED) {
                    grantMicrophone();
                } else {
                    requestPermissions(new String[] { Manifest.permission.RECORD_AUDIO }, MICROPHONE);
                }
            }
            @Override public void onPermissionRequestCanceled(PermissionRequest request) {
                if (microphoneRequest == request) microphoneRequest = null;
            }
            @Override public boolean onCreateWindow(WebView view, boolean dialog, boolean userGesture, Message result) {
                if (!userGesture) return false;
                WebView popup = new WebView(MainActivity.this);
                popup.setWebViewClient(new WebViewClient() {
                    private boolean handled;
                    private void open(Uri uri) {
                        if (handled || "about".equals(uri.getScheme())) return;
                        handled = true;
                        if (!route(uri)) web.loadUrl(uri.toString());
                        popup.post(popup::destroy);
                    }
                    @Override public boolean shouldOverrideUrlLoading(WebView v, WebResourceRequest r) {
                        open(r.getUrl()); return true;
                    }
                    @Override public void onPageStarted(WebView v, String url, android.graphics.Bitmap icon) {
                        open(Uri.parse(url));
                    }
                });
                ((WebView.WebViewTransport) result.obj).setWebView(popup);
                result.sendToTarget();
                return true;
            }
            @Override public void onShowCustomView(View view, CustomViewCallback callback) {
                if (fullScreen != null) { callback.onCustomViewHidden(); return; }
                fullScreen = view;
                fullScreenCallback = callback;
                content.addView(view, new FrameLayout.LayoutParams(-1, -1));
            }
            @Override public void onHideCustomView() { hideFullScreen(); }
        });
        web.setDownloadListener((url, agent, disposition, mime, length) -> download(Uri.parse(url), disposition, mime));
        if (Build.VERSION.SDK_INT >= 33) {
            getOnBackInvokedDispatcher().registerOnBackInvokedCallback(0, this::navigateBack);
        }
        web.loadUrl(HOME);
    }

    private static boolean trusted(Uri uri) {
        return "https".equalsIgnoreCase(uri.getScheme()) && "memo.example.com".equalsIgnoreCase(uri.getHost())
                && (uri.getPort() == -1 || uri.getPort() == 443) && uri.getUserInfo() == null;
    }

    private boolean route(Uri uri) {
        if (trusted(uri)) {
            if (uri.getPath() != null && uri.getPath().matches("/api/files/[0-9]+")) {
                download(uri, null, null);
                return true;
            }
            return false;
        }
        external(uri);
        return true;
    }

    private void external(Uri uri) {
        String scheme = uri.getScheme();
        if (!("https".equalsIgnoreCase(scheme) || "http".equalsIgnoreCase(scheme)
                || "mailto".equalsIgnoreCase(scheme) || "tel".equalsIgnoreCase(scheme))) {
            toast("此链接暂不支持打开。"); return;
        }
        try { startActivity(new Intent(Intent.ACTION_VIEW, uri).addCategory(Intent.CATEGORY_BROWSABLE)); }
        catch (ActivityNotFoundException e) { toast("没有可以打开此链接的应用。"); }
    }

    private void download(Uri uri, String disposition, String mime) {
        if (!trusted(uri)) { external(uri); return; }
        // window.open() supplies only a URL; ask the authenticated endpoint for the original name.
        if (disposition == null && mime == null) {
            String cookie = CookieManager.getInstance().getCookie(uri.toString());
            new Thread(() -> {
                HttpURLConnection connection = null;
                try {
                    connection = (HttpURLConnection) new URL(uri.toString()).openConnection();
                    connection.setRequestMethod("HEAD");
                    connection.setConnectTimeout(10000);
                    connection.setReadTimeout(15000);
                    connection.setInstanceFollowRedirects(false);
                    if (cookie != null) connection.setRequestProperty("Cookie", cookie);
                    int status = connection.getResponseCode();
                    if (status != 200 && status != 206) throw new java.io.IOException("HTTP " + status);
                    String filename = connection.getHeaderField("Content-Disposition");
                    String type = connection.getContentType();
                    runOnUiThread(() -> {
                        if (!isDestroyed()) download(uri, filename == null ? "" : filename,
                                type == null ? "application/octet-stream" : type);
                    });
                } catch (java.io.IOException e) {
                    runOnUiThread(() -> { if (!isDestroyed()) toast("无法获取附件，请检查网络和登录状态后重试。"); });
                } finally { if (connection != null) connection.disconnect(); }
            }, "attachment-download").start();
            return;
        }
        Runnable action = () -> {
            try {
                DownloadManager.Request request = new DownloadManager.Request(uri);
                String cookie = CookieManager.getInstance().getCookie(uri.toString());
                if (cookie != null) request.addRequestHeader("Cookie", cookie);
                request.addRequestHeader("User-Agent", web.getSettings().getUserAgentString());
                request.addRequestHeader("Referer", HOME);
                String name = downloadName(uri, disposition, mime);
                request.setTitle(name);
                if (mime != null) request.setMimeType(mime);
                request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
                ((DownloadManager) getSystemService(DOWNLOAD_SERVICE)).enqueue(request);
                toast("已开始下载，可在系统下载列表查看。");
            } catch (RuntimeException e) { toast("下载未能开始，请检查下载管理器和存储空间。"); }
        };
        if (Build.VERSION.SDK_INT < 29 && checkSelfPermission(Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED) {
            pendingDownload = action;
            requestPermissions(new String[] { Manifest.permission.WRITE_EXTERNAL_STORAGE }, STORAGE);
        } else action.run();
    }

    private String downloadName(Uri uri, String disposition, String mime) {
        if (disposition != null) {
            Matcher matcher = Pattern.compile("filename\\*=UTF-8''([^;]+)", Pattern.CASE_INSENSITIVE).matcher(disposition);
            if (matcher.find()) {
                String name = Uri.decode(matcher.group(1).trim()).replaceAll("[\\\\/\\p{Cntrl}]", "_");
                if (!name.isEmpty() && !name.equals(".") && !name.equals("..")) return name;
            }
        }
        return URLUtil.guessFileName(uri.toString(), disposition, mime).replaceAll("[\\\\/\\p{Cntrl}]", "_");
    }

    private void showMenu(View anchor) {
        PopupMenu menu = new PopupMenu(this, anchor);
        menu.getMenu().add("刷新页面").setOnMenuItemClickListener(item -> {
            new AlertDialog.Builder(this).setTitle("刷新页面？").setMessage("请先保存正在编辑的内容，再刷新获取网站最新版本。")
                    .setNegativeButton("取消", null).setPositiveButton("刷新", (dialog, which) -> web.reload()).show();
            return true;
        });
        menu.getMenu().add("系统下载列表").setOnMenuItemClickListener(item -> {
            try { startActivity(new Intent(DownloadManager.ACTION_VIEW_DOWNLOADS)); }
            catch (ActivityNotFoundException e) { toast("请在文件管理器的下载目录查看。"); }
            return true;
        });
        menu.getMenu().add("在浏览器打开").setOnMenuItemClickListener(item -> { external(Uri.parse(HOME)); return true; });
        menu.show();
    }

    private void setupError() {
        error = new LinearLayout(this);
        error.setOrientation(LinearLayout.VERTICAL);
        error.setGravity(Gravity.CENTER);
        error.setPadding(dp(24), dp(24), dp(24), dp(24));
        error.setBackgroundColor(Color.WHITE);
        errorMessage = new TextView(this);
        errorMessage.setTextSize(16);
        errorMessage.setTextColor(Color.rgb(78, 89, 105));
        errorMessage.setGravity(Gravity.CENTER);
        error.addView(errorMessage);
        Button retry = new Button(this);
        retry.setText("重新连接");
        retry.setMinHeight(dp(48));
        retry.setOnClickListener(v -> web.loadUrl(retryUrl));
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(-2, -2);
        params.topMargin = dp(16);
        error.addView(retry, params);
        error.setVisibility(View.GONE);
        content.addView(error, new FrameLayout.LayoutParams(-1, -1));
    }

    private void showError(String message) { errorMessage.setText(message); error.setVisibility(View.VISIBLE); progress.setVisibility(View.INVISIBLE); }
    private void toast(String message) { Toast.makeText(this, message, Toast.LENGTH_LONG).show(); }
    private int dp(int value) { return Math.round(value * getResources().getDisplayMetrics().density); }
    private boolean hasAudio(PermissionRequest request) {
        for (String resource : request.getResources()) if (PermissionRequest.RESOURCE_AUDIO_CAPTURE.equals(resource)) return true;
        return false;
    }
    private void grantMicrophone() {
        if (microphoneRequest != null) {
            microphoneRequest.grant(new String[] { PermissionRequest.RESOURCE_AUDIO_CAPTURE });
            microphoneRequest = null;
        }
    }
    @Override public void onRequestPermissionsResult(int code, String[] permissions, int[] results) {
        super.onRequestPermissionsResult(code, permissions, results);
        boolean granted = results.length > 0 && results[0] == PackageManager.PERMISSION_GRANTED;
        if (code == MICROPHONE && microphoneRequest != null) {
            if (granted) grantMicrophone();
            else { microphoneRequest.deny(); microphoneRequest = null; toast("未开启麦克风，可在系统设置中允许后重试录音。"); }
        }
        if (code == STORAGE && pendingDownload != null) {
            Runnable action = pendingDownload; pendingDownload = null;
            if (granted) action.run(); else toast("未开启存储权限，下载已取消。");
        }
    }
    @Override protected void onActivityResult(int request, int result, Intent data) {
        super.onActivityResult(request, result, data);
        if (request == FILE_PICKER && fileCallback != null) {
            Uri[] selected = WebChromeClient.FileChooserParams.parseResult(result, data);
            if (selected != null) {
                for (Uri uri : selected) {
                    if (uri == null || !"content".equals(uri.getScheme())) {
                        selected = null;
                        toast("请选择系统文件管理器中的文件。");
                        break;
                    }
                }
            }
            fileCallback.onReceiveValue(selected); fileCallback = null;
        }
    }
    private void hideFullScreen() {
        if (fullScreen != null) {
            content.removeView(fullScreen); fullScreen = null;
            fullScreenCallback.onCustomViewHidden(); fullScreenCallback = null;
        }
    }
    private void navigateBack() {
        if (fullScreen != null) hideFullScreen();
        else if (web.canGoBack()) { error.setVisibility(View.GONE); web.goBack(); }
        else finish();
    }
    @Override public void onBackPressed() { navigateBack(); }
    @Override protected void onPause() { web.onPause(); CookieManager.getInstance().flush(); super.onPause(); }
    @Override protected void onResume() { super.onResume(); if (web != null) web.onResume(); }
    @Override protected void onDestroy() {
        if (fileCallback != null) fileCallback.onReceiveValue(null);
        if (microphoneRequest != null) microphoneRequest.deny();
        pendingDownload = null;
        hideFullScreen();
        content.removeView(web); web.destroy();
        super.onDestroy();
    }
}
