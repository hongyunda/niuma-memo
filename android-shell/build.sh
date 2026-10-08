#!/usr/bin/env bash
# Build a dependency-free remote WebView APK using the installed Android SDK.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP="$ROOT/android-shell"
SDK="${NIUMA_ANDROID_SDK:-/usr/local/share/android-commandlinetools}"
export JAVA_HOME="${NIUMA_JAVA_HOME:-/usr/local/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home}"
export PATH="$JAVA_HOME/bin:$PATH"
TOOLS="$SDK/build-tools/35.0.0"
PLATFORM="$SDK/platforms/android-35/android.jar"
BUILD="$APP/build"
SIGNING="$APP/.signing"
OUT="$ROOT/output/android"
for tool in "$TOOLS/aapt2" "$TOOLS/d8" "$TOOLS/zipalign" "$TOOLS/apksigner" "$JAVA_HOME/bin/javac"; do
  test -x "$tool" || { echo "缺少构建工具：$tool" >&2; exit 1; }
done
test -f "$PLATFORM"
mkdir -p "$BUILD" "$OUT" "$SIGNING"
chmod 700 "$SIGNING"
# Never silently replace an existing release signing identity.
if [[ ! -f "$SIGNING/release.jks" ]]; then
  if [[ -f "$SIGNING/password" ]]; then
    echo '签名密码已存在，但密钥缺失。请先恢复原 release.jks，避免无法覆盖安装。' >&2
    exit 1
  fi
  (umask 077; openssl rand -hex 32 > "$SIGNING/password")
  keytool -genkeypair -keystore "$SIGNING/release.jks" -storetype JKS \
    -storepass:file "$SIGNING/password" -keypass:file "$SIGNING/password" \
    -alias niuma-memo -keyalg RSA -keysize 3072 -validity 10000 \
    -dname 'CN=Niuma Memo, O=Niuma Memo Contributors, C=CN' -noprompt
  chmod 600 "$SIGNING/release.jks"
fi
test -f "$SIGNING/password"
rm -rf "$BUILD/classes" "$BUILD/dex" "$BUILD/res" "$BUILD/generated"
mkdir -p "$BUILD/classes" "$BUILD/dex" "$BUILD/res/drawable" "$BUILD/generated"
cp -R "$APP/res/." "$BUILD/res/"
cp "$ROOT/web/public/icons/icon-192.png" "$BUILD/res/drawable/app_icon.png"
"$TOOLS/aapt2" compile --dir "$BUILD/res" -o "$BUILD/resources.zip"
"$TOOLS/aapt2" link -I "$PLATFORM" --manifest "$APP/AndroidManifest.xml" \
  --java "$BUILD/generated" -o "$BUILD/base.apk" "$BUILD/resources.zip"
find "$APP/src" "$BUILD/generated" -name '*.java' -print > "$BUILD/sources.txt"
# javac argfile paths must be quoted when the checkout contains spaces.
python3 - "$BUILD/sources.txt" <<'PY'
import pathlib, sys
p = pathlib.Path(sys.argv[1])
p.write_text(''.join('"' + line.replace('\\', '\\\\').replace('"', '\\"') + '"\n' for line in p.read_text().splitlines()))
PY
javac --release 8 -encoding UTF-8 -classpath "$PLATFORM" -d "$BUILD/classes" @"$BUILD/sources.txt"
jar cf "$BUILD/classes.jar" -C "$BUILD/classes" .
"$TOOLS/d8" --release --min-api 26 --lib "$PLATFORM" --output "$BUILD/dex" "$BUILD/classes.jar"
cp "$BUILD/base.apk" "$BUILD/unsigned.apk"
(cd "$BUILD/dex" && zip -q "$BUILD/unsigned.apk" classes*.dex)
"$TOOLS/zipalign" -f -p 4 "$BUILD/unsigned.apk" "$BUILD/aligned.apk"
"$TOOLS/apksigner" sign --ks "$SIGNING/release.jks" --ks-key-alias niuma-memo \
  --ks-pass "file:$SIGNING/password" \
  --v4-signing-enabled false --out "$OUT/niuma-memo-1.0.0.apk" "$BUILD/aligned.apk"
"$TOOLS/apksigner" verify --verbose --print-certs "$OUT/niuma-memo-1.0.0.apk"
"$TOOLS/zipalign" -c -p 4 "$OUT/niuma-memo-1.0.0.apk"
(cd "$OUT" && shasum -a 256 niuma-memo-1.0.0.apk > niuma-memo-1.0.0.apk.sha256)
echo "APK 已生成：$OUT/niuma-memo-1.0.0.apk"
