package pa.innovate.distribuidora.impresora;

import android.Manifest;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothSocket;
import android.content.Context;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.Build;

import java.io.IOException;
import java.io.OutputStream;
import java.lang.reflect.Method;
import java.util.ArrayList;
import java.util.List;
import java.util.Set;
import java.util.UUID;

/**
 * Envío por Bluetooth clásico (perfil serie SPP) a la impresora emparejada en el teléfono.
 * La impresora elegida se guarda por su dirección MAC.
 */
final class Impresora {

    private static final UUID SPP = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB");
    private static final String PREFS = "impresora";
    private static final String CLAVE_MAC = "mac";
    private static final String CLAVE_NOMBRE = "nombre";

    private Impresora() {}

    static boolean tienePermiso(Context c) {
        return Build.VERSION.SDK_INT < 31
            || c.checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT) == PackageManager.PERMISSION_GRANTED;
    }

    static BluetoothAdapter adaptador() {
        return BluetoothAdapter.getDefaultAdapter();
    }

    /** Dispositivos emparejados en el Bluetooth del teléfono. */
    @SuppressWarnings("MissingPermission")
    static List<BluetoothDevice> emparejados() {
        List<BluetoothDevice> lista = new ArrayList<>();
        BluetoothAdapter a = adaptador();
        if (a == null) return lista;
        Set<BluetoothDevice> set = a.getBondedDevices();
        if (set != null) lista.addAll(set);
        return lista;
    }

    @SuppressWarnings("MissingPermission")
    static String nombre(BluetoothDevice d) {
        String n = d.getName();
        return n == null || n.isEmpty() ? d.getAddress() : n;
    }

    static void guardar(Context c, BluetoothDevice d) {
        prefs(c).edit().putString(CLAVE_MAC, d.getAddress()).putString(CLAVE_NOMBRE, nombre(d)).apply();
    }

    static String macGuardada(Context c) { return prefs(c).getString(CLAVE_MAC, null); }
    static String nombreGuardado(Context c) { return prefs(c).getString(CLAVE_NOMBRE, null); }

    /**
     * Impresora a usar: la guardada; si no hay, la única emparejada que parezca impresora
     * (y queda guardada). null = hay que elegirla en la app.
     */
    static String macAUsar(Context c) {
        String mac = macGuardada(c);
        if (mac != null) return mac;
        BluetoothDevice candidata = null;
        int cuantas = 0;
        for (BluetoothDevice d : emparejados()) {
            String n = nombre(d).toLowerCase();
            if (n.contains("print") || n.contains("xp-") || n.contains("p503") || n.contains("pos") || n.contains("mtp")) {
                candidata = d;
                cuantas++;
            }
        }
        if (cuantas == 1) {
            guardar(c, candidata);
            return candidata.getAddress();
        }
        return null;
    }

    /** Conecta, escribe y cierra. Lanza IOException con un mensaje para mostrar. */
    @SuppressWarnings("MissingPermission")
    static void imprimir(String mac, byte[] datos) throws IOException {
        BluetoothAdapter a = adaptador();
        if (a == null) throw new IOException("Este teléfono no tiene Bluetooth");
        if (!a.isEnabled()) throw new IOException("El Bluetooth está apagado");
        BluetoothDevice d = a.getRemoteDevice(mac);
        try { a.cancelDiscovery(); } catch (Exception ignorada) { }

        BluetoothSocket s = conectar(d);
        try {
            OutputStream out = s.getOutputStream();
            final int trozo = 512;
            for (int i = 0; i < datos.length; i += trozo) {
                out.write(datos, i, Math.min(trozo, datos.length - i));
                out.flush();
                dormir(15); // no llenar el búfer de la impresora
            }
            dormir(400);    // que termine de imprimir antes de cortar la conexión
        } finally {
            try { s.close(); } catch (IOException ignorada) { }
        }
    }

    @SuppressWarnings("MissingPermission")
    private static BluetoothSocket conectar(BluetoothDevice d) throws IOException {
        IOException ultimo = null;
        // 1) SPP seguro  2) SPP sin cifrar  3) canal 1 directo (algunas impresoras chinas)
        for (int intento = 0; intento < 3; intento++) {
            BluetoothSocket s = null;
            try {
                if (intento == 0) s = d.createRfcommSocketToServiceRecord(SPP);
                else if (intento == 1) s = d.createInsecureRfcommSocketToServiceRecord(SPP);
                else {
                    Method m = d.getClass().getMethod("createRfcommSocket", int.class);
                    s = (BluetoothSocket) m.invoke(d, 1);
                }
                s.connect();
                return s;
            } catch (Exception e) {
                ultimo = e instanceof IOException ? (IOException) e : new IOException(e.getMessage());
                if (s != null) try { s.close(); } catch (IOException ignorada) { }
                dormir(300);
            }
        }
        throw new IOException("No se pudo conectar con la impresora (¿está encendida y cerca?)"
            + (ultimo != null && ultimo.getMessage() != null ? ": " + ultimo.getMessage() : ""));
    }

    /** Ticket de prueba en ESC/POS. */
    static byte[] prueba(String impresora) {
        StringBuilder t = new StringBuilder();
        t.append("Distribuidora Arango\n");
        t.append("--------------------------------\n");
        t.append("12345678901234567890123456789012\n");
        t.append("Impresora: ").append(sinAcentos(impresora)).append('\n');
        t.append("Si esta linea llega al borde,\nel ancho (32) esta bien.\n");
        t.append("--------------------------------\n");
        byte[] cuerpo = t.toString().getBytes(java.nio.charset.StandardCharsets.US_ASCII);
        byte[] inicio = { 0x1b, 0x40, 0x1b, 0x61, 1, 0x1d, 0x21, 0x11 };
        byte[] titulo = "PRUEBA OK\n".getBytes(java.nio.charset.StandardCharsets.US_ASCII);
        byte[] normal = { 0x1d, 0x21, 0x00, 0x1b, 0x61, 0 };
        byte[] fin = { 0x1b, 0x64, 4 };
        byte[] todo = new byte[inicio.length + titulo.length + normal.length + cuerpo.length + fin.length];
        int p = 0;
        for (byte[] b : new byte[][] { inicio, titulo, normal, cuerpo, fin }) {
            System.arraycopy(b, 0, todo, p, b.length);
            p += b.length;
        }
        return todo;
    }

    static String sinAcentos(String s) {
        if (s == null) return "";
        String n = java.text.Normalizer.normalize(s, java.text.Normalizer.Form.NFD);
        return n.replaceAll("[\\u0300-\\u036f]", "").replaceAll("[^\\x20-\\x7E]", "");
    }

    private static SharedPreferences prefs(Context c) {
        return c.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    static void dormir(long ms) {
        try { Thread.sleep(ms); } catch (InterruptedException ignorada) { Thread.currentThread().interrupt(); }
    }
}
