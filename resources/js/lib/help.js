/**
 * Plain-language explanations shown next to each section and metric of the
 * report. Keys match the section and check keys returned by the API.
 */

export const SECTION_HELP = {
    technical:
        'Aspectos técnicos que permiten a los buscadores acceder a la página, rastrearla y mostrarla bien en móviles.',
    meta: 'Etiquetas del <head> que controlan cómo aparece la página en los buscadores y al compartirla en redes sociales.',
    headings:
        'Estructura de títulos (H1–H6) que organiza el contenido para las personas, los lectores de pantalla y los buscadores.',
    content:
        'Cantidad de texto del contenido principal, términos más repetidos e imágenes sin texto alternativo.',
    links: 'Enlaces de la página y comprobación de cuáles están rotos. Se comprueba un número limitado de enlaces por auditoría.',
    performance:
        'Velocidad de carga medida con PageSpeed Insights (Lighthouse) en condiciones de laboratorio.',
};

export const CHECK_HELP = {
    http_status:
        'La página debe responder con un código 2xx (normalmente 200) para poder indexarse.',
    https: 'HTTPS cifra la conexión. Es una señal de posicionamiento y evita el aviso «No seguro» del navegador.',
    redirects:
        'Cada redirección añade tiempo de carga; lo ideal es enlazar directamente a la URL final.',
    response_time:
        'Tiempo que tardó en descargarse el HTML desde el servidor de esta herramienta (varía según la ubicación).',
    page_size: 'Peso del documento HTML, sin contar imágenes, hojas de estilo ni JavaScript.',
    lang: 'El atributo lang de <html> indica el idioma a buscadores, traductores y lectores de pantalla.',
    viewport: 'La etiqueta viewport adapta el ancho de la página a la pantalla de los móviles.',
    robots_txt: 'Archivo que indica a los buscadores qué partes del sitio pueden rastrear.',
    sitemap: 'Listado XML de URL que ayuda a los buscadores a descubrir las páginas del sitio.',
    title: 'Título de la pestaña y del resultado en buscadores. Recomendado: entre 30 y 60 caracteres.',
    meta_description:
        'Resumen que los buscadores suelen mostrar bajo el título. Recomendado: entre 70 y 160 caracteres.',
    canonical:
        'Indica la URL preferida cuando el mismo contenido es accesible desde varias direcciones.',
    robots: 'Directivas para buscadores: «noindex» impide que la página aparezca en los resultados.',
    open_graph:
        'Etiquetas og:* que definen el título, el texto y la imagen al compartir la página en redes sociales.',
    h1: 'Encabezado principal: debe existir y resumir el tema de la página.',
    hierarchy: 'Los niveles deben usarse en orden (H1 → H2 → H3) sin saltarse ninguno.',
    empty_headings:
        'Encabezados sin texto: no aportan estructura y confunden a los lectores de pantalla.',
    total: 'Número total de encabezados H1–H6 encontrados.',
    word_count:
        'Palabras del contenido principal, sin menú, pie de página ni scripts. Por debajo de 300 se considera contenido breve.',
    top_term:
        'Término que más se repite, sin contar palabras vacías. La densidad es orientativa: no es un factor de posicionamiento por sí misma.',
    images_alt:
        'El texto alternativo (alt) describe las imágenes a lectores de pantalla y buscadores. alt="" marca una imagen decorativa.',
    total_links: 'Enlaces a páginas web encontrados en el documento.',
    internal_links:
        'Enlaces a otras páginas del mismo sitio: ayudan a descubrirlas y a entender su estructura.',
    external_links: 'Enlaces a otros sitios web.',
    broken_links: 'Enlaces que devuelven un error (4xx o 5xx) o que no responden.',
    links_without_text:
        'Enlaces sin texto accesible: los lectores de pantalla no pueden explicar a dónde llevan.',
    unchecked_links: 'Enlaces que no se comprobaron por el límite configurado para cada auditoría.',
    performance_score:
        'Puntuación de rendimiento de Lighthouse (0–100) medida en condiciones de laboratorio.',
    lcp: 'Tiempo hasta que se muestra el elemento más grande visible. Bueno: 2,5 s o menos.',
    cls: 'Cuánto se desplaza el contenido mientras carga. Bueno: 0,1 o menos.',
    fcp: 'Tiempo hasta que aparece el primer contenido. Bueno: 1,8 s o menos.',
    tbt: 'Tiempo en el que la página no responde por tareas largas de JavaScript. Bueno: 200 ms o menos.',
    speed_index: 'Rapidez con la que se muestra visualmente el contenido. Bueno: 3,4 s o menos.',
};
