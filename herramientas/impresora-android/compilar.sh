#!/usr/bin/env bash
# Compila la app "Impresora Distribuidora" sin Gradle (aapt2 + javac + d8 + apksigner) y deja el APK
# firmado en public/descargas/ImpresoraDistribuidora.apk.
#
#   bash herramientas/impresora-android/compilar.sh [versionCode] [versionName]
#
# Herramientas (fuera del repo): C:\laragon\herramientas\android  (jdk-17*, sdk/build-tools/34.0.0, sdk/platforms/android-34)
# Llave de firma (NO va al repo, que es público): C:\laragon\respaldos\firma-impresora\impresora.jks
#   Si se pierde, los celulares no aceptan actualizaciones: habría que desinstalar e instalar de nuevo.
set -euo pipefail

VCODE="${1:-1}"
VNAME="${2:-1.0}"
AQUI="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$AQUI/../.." && pwd)"
T=/c/laragon/herramientas/android
JDK="$(ls -d "$T"/jdk-17* | head -1)"
BT="$T/sdk/build-tools/34.0.0"
JAR="$T/sdk/platforms/android-34/android.jar"
FIRMA_DIR=/c/laragon/respaldos/firma-impresora
KS="$FIRMA_DIR/impresora.jks"
KS_PASS_FILE="$FIRMA_DIR/clave.txt"
SALIDA="$REPO/public/descargas/ImpresoraDistribuidora.apk"
B="$AQUI/build"

export JAVA_HOME="$JDK"
export PATH="$JDK/bin:$PATH"
w() { cygpath -w "$1"; }

rm -rf "$B"; mkdir -p "$B/res" "$B/clases" "$B/dex"

# Llave de firma: se crea una sola vez y se guarda en respaldos
if [ ! -f "$KS" ]; then
  mkdir -p "$FIRMA_DIR"
  head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' > "$KS_PASS_FILE"
  keytool -genkeypair -keystore "$(w "$KS")" -alias impresora -keyalg RSA -keysize 2048 -validity 10000 \
    -storepass "$(cat "$KS_PASS_FILE")" -keypass "$(cat "$KS_PASS_FILE")" \
    -dname "CN=Distribuidora Arango, O=Distribuidora Arango, C=PA" >/dev/null
  echo "Llave nueva creada en $KS (guárdala: sin ella no se puede actualizar la app)"
fi
CLAVE="$(cat "$KS_PASS_FILE")"

# 1) Recursos
"$BT/aapt2.exe" compile --dir "$(w "$AQUI/res")" -o "$(w "$B/res.zip")"
"$BT/aapt2.exe" link -o "$(w "$B/base.apk")" -I "$(w "$JAR")" --manifest "$(w "$AQUI/AndroidManifest.xml")" \
  --min-sdk-version 26 --target-sdk-version 34 --version-code "$VCODE" --version-name "$VNAME" "$(w "$B/res.zip")"

# 2) Java → .class → classes.dex
find "$AQUI/src" -name '*.java' > "$B/fuentes.txt"
javac -encoding UTF-8 -source 8 -target 8 -nowarn -Xlint:-options -classpath "$(w "$JAR")" -d "$(w "$B/clases")" $(cat "$B/fuentes.txt" | while read f; do w "$f"; done)
"$BT/d8.bat" --release --min-api 26 --lib "$(w "$JAR")" --output "$(w "$B/dex")" $(find "$B/clases" -name '*.class' | while read f; do w "$f"; done)

# 3) Meter classes.dex en el APK, alinear y firmar
cp "$B/base.apk" "$B/sin_firmar.apk"
( cd "$B/dex" && "$BT/aapt.exe" add "$(w "$B/sin_firmar.apk")" classes.dex >/dev/null )
"$BT/zipalign.exe" -f -p 4 "$(w "$B/sin_firmar.apk")" "$(w "$B/alineado.apk")"
mkdir -p "$(dirname "$SALIDA")"
"$BT/apksigner.bat" sign --ks "$(w "$KS")" --ks-key-alias impresora --ks-pass "pass:$CLAVE" --key-pass "pass:$CLAVE" \
  --out "$(w "$SALIDA")" "$(w "$B/alineado.apk")"
"$BT/apksigner.bat" verify "$(w "$SALIDA")"

rm -rf "$B"
echo "APK listo: $SALIDA ($(wc -c < "$SALIDA") bytes) versión $VNAME ($VCODE)"
