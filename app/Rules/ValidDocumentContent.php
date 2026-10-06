<?php

namespace App\Rules;

use Closure;
use DOMDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Que el contenido del archivo sea de verdad lo que dice su extensión: un PDF
 * renombrado a .docx, un .docx dañado o un texto con extensión .xml no pasan.
 *
 * No se usa `mimes` porque adivina el tipo con firmas genéricas: un Word o un
 * Excel generados por otras herramientas suelen salir como «zip» y se
 * rechazarían siendo válidos. Aquí se revisa la estructura de cada tipo:
 *
 * - docx / xlsx: un ZIP con `[Content_Types].xml` y su documento principal.
 * - doc / xls: la firma de los archivos compuestos de Office 97-2003 (OLE).
 * - pdf: la cabecera `%PDF-`.
 * - imágenes: se pueden leer como JPG, PNG o WEBP.
 * - xml: que esté bien formado.
 *
 * La lista de extensiones permitidas la pone la regla `extensions`.
 */
class ValidDocumentContent implements ValidationRule
{
    /** Los primeros 8 bytes de todo archivo compuesto de Office 97-2003. */
    private const OLE_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $path = $value->getRealPath();
        $extension = strtolower($value->getClientOriginalExtension());

        [$valid, $kind] = match ($extension) {
            'docx' => [$this->isOpenXml($path, 'word/document.xml'), 'un documento de Word válido'],
            'xlsx' => [$this->isOpenXml($path, 'xl/workbook.xml'), 'un libro de Excel válido'],
            'doc' => [$this->startsWith($path, self::OLE_SIGNATURE), 'un documento de Word válido'],
            'xls' => [$this->startsWith($path, self::OLE_SIGNATURE), 'un libro de Excel válido'],
            'pdf' => [$this->startsWith($path, '%PDF-'), 'un PDF válido'],
            'jpg', 'jpeg', 'png', 'webp' => [$this->isImage($path), 'una imagen válida'],
            'xml' => [$this->isXml($path), 'un XML válido'],
            default => [true, null],
        };

        if (! $valid) {
            $fail("«{$value->getClientOriginalName()}» no es {$kind} o está dañado.");
        }
    }

    private function isOpenXml(string $path, string $mainPart): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        $valid = $zip->locateName('[Content_Types].xml') !== false
            && $zip->locateName($mainPart) !== false;

        $zip->close();

        return $valid;
    }

    private function startsWith(string $path, string $signature): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, strlen($signature));
        fclose($handle);

        return $header === $signature;
    }

    /**
     * Cualquiera de las tres sirve aunque no coincida con la extensión: un PNG
     * guardado como .jpg sigue siendo una imagen que se puede abrir.
     */
    private function isImage(string $path): bool
    {
        $info = @getimagesize($path);

        return $info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
    }

    /** Sin red ni entidades externas: solo se revisa que sea XML bien formado. */
    private function isXml(string $path): bool
    {
        $previous = libxml_use_internal_errors(true);

        $valid = (new DOMDocument)->load($path, LIBXML_NONET) !== false;

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $valid;
    }
}
