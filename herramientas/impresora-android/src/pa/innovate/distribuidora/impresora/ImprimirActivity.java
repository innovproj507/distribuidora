package pa.innovate.distribuidora.impresora;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.util.Base64;
import android.widget.Toast;

import java.io.IOException;

/**
 * Recibe el ticket del sistema: distprint:base64,<ESC/POS en base64>
 * (desde Chrome: intent:base64,…#Intent;scheme=distprint;package=…;end). Imprime y se cierra.
 * Si falta el permiso o la impresora, abre la pantalla principal con el ticket pendiente.
 */
public class ImprimirActivity extends Activity {

    @Override
    protected void onCreate(Bundle estado) {
        super.onCreate(estado);
        byte[] datos = leer(getIntent());
        if (datos == null || datos.length == 0) {
            aviso("El ticket llegó vacío");
            finish();
            return;
        }
        String mac = Impresora.tienePermiso(this) ? Impresora.macAUsar(this) : null;
        if (mac == null) {
            // Primera vez: elegir impresora / dar permiso; MainActivity imprime el pendiente después
            Intent i = new Intent(this, MainActivity.class);
            i.putExtra(MainActivity.EXTRA_PENDIENTE, datos);
            i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            startActivity(i);
            finish();
            return;
        }
        final String destino = mac;
        aviso("Imprimiendo en " + (Impresora.nombreGuardado(this) != null ? Impresora.nombreGuardado(this) : "la impresora") + "…");
        new Thread(() -> {
            String mensaje = null;
            try {
                Impresora.imprimir(destino, datos);
            } catch (IOException e) {
                mensaje = e.getMessage();
            }
            final String error = mensaje;
            runOnUiThread(() -> {
                if (error != null) aviso("No se imprimió: " + error);
                finish();
            });
        }).start();
    }

    private static byte[] leer(Intent intent) {
        if (intent == null) return null;
        Uri uri = intent.getData();
        if (uri == null) return null;
        String parte = uri.getSchemeSpecificPart();   // "base64,AAAA…"
        if (parte == null) return null;
        int coma = parte.indexOf(',');
        String b64 = coma >= 0 ? parte.substring(coma + 1) : parte;
        try {
            return Base64.decode(b64.replace(' ', '+'), Base64.DEFAULT);
        } catch (IllegalArgumentException e) {
            return null;
        }
    }

    private void aviso(String texto) {
        Toast.makeText(getApplicationContext(), texto, Toast.LENGTH_LONG).show();
    }
}
