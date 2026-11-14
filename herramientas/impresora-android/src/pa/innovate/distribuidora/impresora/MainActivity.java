package pa.innovate.distribuidora.impresora;

import android.Manifest;
import android.app.Activity;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.Typeface;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.RadioButton;
import android.widget.RadioGroup;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import java.io.IOException;
import java.util.List;

/**
 * Elegir la impresora emparejada e imprimir una prueba. Si llegó un ticket antes de configurar
 * (EXTRA_PENDIENTE), se imprime apenas se elige la impresora.
 */
public class MainActivity extends Activity {

    static final String EXTRA_PENDIENTE = "pendiente";
    private static final int PEDIR_PERMISO = 7;
    private static final int AZUL = Color.rgb(15, 46, 107);

    private LinearLayout raiz;
    private TextView estado;
    private RadioGroup grupo;
    private byte[] pendiente;

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        pendiente = getIntent().getByteArrayExtra(EXTRA_PENDIENTE);
        setTitle("Impresora Distribuidora");

        ScrollView scroll = new ScrollView(this);
        raiz = new LinearLayout(this);
        raiz.setOrientation(LinearLayout.VERTICAL);
        int pad = dp(20);
        raiz.setPadding(pad, pad, pad, pad);
        scroll.addView(raiz);
        setContentView(scroll);

        titulo("Impresora de tickets");
        texto("El sistema (Vender → Imprimir ticket) manda el ticket a esta app y ella lo imprime por Bluetooth. "
            + "Primero empareja la impresora en Ajustes → Bluetooth (clave 0000 o 1234) y elígela aquí.", 14, Color.DKGRAY);

        estado = texto("", 15, AZUL);
        estado.setTypeface(Typeface.DEFAULT_BOLD);
        estado.setPadding(0, dp(12), 0, dp(8));

        grupo = new RadioGroup(this);
        raiz.addView(grupo);

        boton("Imprimir prueba", true, v -> imprimirPrueba());
        boton("Abrir ajustes de Bluetooth", false, v -> startActivity(new Intent(Settings.ACTION_BLUETOOTH_SETTINGS)));
        boton("Actualizar lista", false, v -> cargar());

        texto("\nEn el sistema: Más → Impresora de tickets → modo \"App Impresora Distribuidora\".", 13, Color.GRAY);

        if (!Impresora.tienePermiso(this) && Build.VERSION.SDK_INT >= 31) {
            requestPermissions(new String[] { Manifest.permission.BLUETOOTH_CONNECT }, PEDIR_PERMISO);
        } else {
            cargar();
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (Impresora.tienePermiso(this) && grupo != null && grupo.getChildCount() == 0) cargar();
    }

    @Override
    public void onRequestPermissionsResult(int codigo, String[] permisos, int[] resultados) {
        if (codigo == PEDIR_PERMISO) {
            if (Impresora.tienePermiso(this)) cargar();
            else estado.setText("Sin permiso de Bluetooth: ábrela de nuevo y toca Permitir.");
        }
    }

    private void cargar() {
        grupo.removeAllViews();
        BluetoothAdapter a = Impresora.adaptador();
        if (a == null) { estado.setText("Este teléfono no tiene Bluetooth."); return; }
        if (!Impresora.tienePermiso(this)) { estado.setText("Falta el permiso de Bluetooth."); return; }
        if (!a.isEnabled()) { estado.setText("Enciende el Bluetooth del teléfono."); return; }

        List<BluetoothDevice> lista = Impresora.emparejados();
        if (lista.isEmpty()) {
            estado.setText("No hay equipos emparejados. Empareja la impresora en Ajustes → Bluetooth.");
            return;
        }
        String elegida = Impresora.macGuardada(this);
        for (BluetoothDevice d : lista) {
            RadioButton r = new RadioButton(this);
            r.setText(Impresora.nombre(d) + "\n" + d.getAddress());
            r.setTextSize(16);
            r.setPadding(dp(8), dp(10), 0, dp(10));
            r.setId(View.generateViewId());
            r.setChecked(d.getAddress().equals(elegida));
            r.setOnClickListener(v -> elegir(d));
            grupo.addView(r);
        }
        mostrarEstado();
    }

    private void elegir(BluetoothDevice d) {
        Impresora.guardar(this, d);
        mostrarEstado();
        if (pendiente != null) {
            byte[] datos = pendiente;
            pendiente = null;
            enviar(datos, "Ticket impreso");
        }
    }

    private void mostrarEstado() {
        String n = Impresora.nombreGuardado(this);
        estado.setText(n == null ? "Toca tu impresora en la lista:" : "Impresora: " + n);
    }

    private void imprimirPrueba() {
        if (Impresora.macGuardada(this) == null) { aviso("Primero elige la impresora en la lista"); return; }
        enviar(Impresora.prueba(Impresora.nombreGuardado(this)), "Prueba enviada");
    }

    private void enviar(byte[] datos, String ok) {
        String mac = Impresora.macGuardada(this);
        if (mac == null) return;
        aviso("Imprimiendo…");
        new Thread(() -> {
            String error = null;
            try { Impresora.imprimir(mac, datos); } catch (IOException e) { error = e.getMessage(); }
            final String err = error;
            runOnUiThread(() -> aviso(err == null ? ok : "No se imprimió: " + err));
        }).start();
    }

    // ── Vista sin XML ──
    private TextView titulo(String t) {
        TextView v = texto(t, 22, AZUL);
        v.setTypeface(Typeface.DEFAULT_BOLD);
        v.setPadding(0, 0, 0, dp(8));
        return v;
    }

    private TextView texto(String t, int sp, int color) {
        TextView v = new TextView(this);
        v.setText(t);
        v.setTextSize(sp);
        v.setTextColor(color);
        raiz.addView(v);
        return v;
    }

    private void boton(String t, boolean principal, View.OnClickListener accion) {
        Button b = new Button(this);
        b.setText(t);
        b.setAllCaps(false);
        b.setGravity(Gravity.CENTER);
        if (principal) { b.setBackgroundColor(AZUL); b.setTextColor(Color.WHITE); }
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, dp(52));
        lp.topMargin = dp(10);
        b.setOnClickListener(accion);
        raiz.addView(b, lp);
    }

    private int dp(int v) {
        return Math.round(v * getResources().getDisplayMetrics().density);
    }

    private void aviso(String t) {
        Toast.makeText(this, t, Toast.LENGTH_LONG).show();
    }
}
