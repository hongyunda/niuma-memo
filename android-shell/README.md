# Android 网页壳

Android 8.0+ 的在线 WebView 外壳，包名 `cn.nmsoft.bwl`。公开源码使用 `https://memo.example.com/` 占位地址；请在 `src/cn/nmsoft/bwl/MainActivity.java` 同时修改 `HOME` 和 `trusted()` 的域名校验，再构建自己的应用。

支持文件选择、录音授权、附件下载、视频全屏和系统返回。站外链接交给浏览器处理，录音与登录 Cookie 仅用于受信任站点。需要网络及可工作的 Android System WebView；不提供离线业务或原生后台推送。

## 构建

需要 JDK 21、Android SDK Platform 35、Build Tools 35.0.0、Python 3、OpenSSL 和 zip。通过变量配置工具路径：

```bash
NIUMA_JAVA_HOME=/path/to/jdk-21 \
NIUMA_ANDROID_SDK=/path/to/android-sdk \
bash android-shell/build.sh
```

脚本内置的默认路径适用于部分 Homebrew 环境，其他系统请明确设置上述变量。输出为 `output/android/niuma-memo-1.0.0.apk` 及校验文件。

首次构建为你生成独立签名，保存在 `android-shell/.signing/`。该目录和构建产物已被 Git 忽略；请自行私下备份签名，升级应用时保留同一签名并递增 `AndroidManifest.xml` 版本。不要提交签名、密码或把它们放到公开下载目录。

源码不包含生产签名或预编译 APK。重新分发时建议使用自己的包名与应用标识，并在目标手机上实测登录、文件上传下载、麦克风、后台恢复和返回操作。鸿蒙原生 HAP 不在本项目内，兼容层安装能力由设备环境决定。
