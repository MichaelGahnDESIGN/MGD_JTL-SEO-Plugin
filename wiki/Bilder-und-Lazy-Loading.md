# Bilder und Lazy Loading

Bilder sind in Shops häufig der größte übertragene Datentyp. Gute Performance entsteht aus mehreren Maßnahmen: passende Abmessungen, moderne Formate, sinnvolle Kompression und korrektes Ladeverhalten.

## Moderne Formate

WebP und AVIF können gegenüber großen PNG- oder JPEG-Dateien erhebliche Einsparungen bringen. Das Plugin konvertiert in Version 1.1.0 noch keine Bilder automatisch. Bildkonvertierung und responsive Varianten sind für spätere Versionen vorgesehen.

## `decoding="async"`

Das Plugin ergänzt diesen Hinweis, wenn ein Bild noch keine Decoding-Vorgabe besitzt. Das verhindert nicht den Download und ist nicht mit Lazy Loading gleichzusetzen.

## Zusätzliches Lazy Loading

Diese Option ist bewusst standardmäßig deaktiviert. JTL, Template oder andere Plugins können bereits Ladeattribute setzen. Falls die Option aktiviert wird, überspringt das Plugin zunächst eine konfigurierbare Zahl früher Inhaltsbilder und bearbeitet nur Bilder ohne bestehendes `loading`-Attribut.

## Zukunft

Geplant sind ein Bild-Audit, fehlende `width`/`height`-Attribute, überdimensionierte Dateien, moderne Formate und eine sichere CLS-Unterstützung.
