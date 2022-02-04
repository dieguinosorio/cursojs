/*console.log(window)//Representa el objeto de la pagina o contexto actual
console.log(document)//Representa el mapa del documento html

//speechSynthesis es una de las tantas api de javascrip
let texto ="Hola soy diego, estoy aprendiendo javascript desde cero...";
const hablar = (texto) =>speechSynthesis.speak(new SpeechSynthesisUtterance(texto));
hablar(texto);*/

/* estos son metodos que s epueden usar directamente desde document*/
/*console.log('************Elementos del Documento*****************')
console.log(window.document)
console.log(document)
console.log(document.head)
console.log(document.body)
console.log(document.html)//undefined
console.log(document.documentElement)//html sin doctype
console.log(document.doctype)
console.log(document.characterSet)
console.log(document.title)
console.log(document.links)//Obtiene un listado de links de la pagina htmlcollection para usarlo hay que convertirlo a un arreglo javascript
console.log(document.images)//Obtiene un listado de imagenes htmlcollection para usarlo hay que convertirlo a un arreglo javascript
console.log(document.forms)//Obtiene un listado de imagenes htmlcollection para usarlo hay que convertirlo a un arreglo javascript
console.log(document.styleSheets)//Obtiene un listado de estilos htmlcollection para usarlo hay que convertirlo a un arreglo javascript
console.log(document.scripts)//Obtiene un listado de scripts htmlcollection para usarlo hay que convertirlo a un arreglo javascript
setTimeout(() => {
    console.log(document.getSelection().toString())
},3000);
console.log(document.write("<h1>Hola mundo desde document</h1>"))//inserta codigo html en la pagina*/

//Metodos obsoletos que se utilizaban antes

console.log(document.getElementsByTagName('li'))
console.log(document.getElementsByName('nombre'))
console.log(document.getElementById('menu'))

//Nuevos aunque querySelector es mas facil de usar por id y class o name es mas rapido por que no hay que poner . o # estos son los mas usador por los prog.
//Retorna NodeList
console.log(document.getElementById('menu'))
console.log(document.querySelector('#menu'))//Consulta de selector recibe como parametro un id,class,name,etiqueta html
console.log(document.querySelector('a'))//Solo trae el primer registro que concida con la busqueda
console.log(document.querySelectorAll('a'))//Trae todos los registros encontrados
console.log(document.querySelectorAll('a').length)
document.querySelectorAll('a').forEach(e=>{ console.log(e) })
console.log(document.querySelectorAll('.card'))
console.log(document.querySelectorAll('.card')[0])
console.log(document.querySelectorAll('#menu li'))

console.clear();

//DOM: Atributos y Data-Attributes 
console.log(document.documentElement.lang) //Estos dos parecen imprimir lo mismo pero en realidad son diferentes
console.log(document.documentElement.getAttribute('lang'))
console.log(document.querySelector('.link-dom').href)// este imprime el link http://127.0.0.1:5500/dom/dom.html
console.log(document.querySelector('.link-dom').getAttribute('href')) //este imprime el nombre del archivo del href href="dom.html"
document.documentElement.lang = "en";
document.documentElement.setAttribute('lang','en');

//A las variables en javascript que se les antepone $ hace referencia a etiquetas del dom y son buenas practicas de prog.
const $linkDom = document.querySelector('.link-dom');
//Le podemos agregar mas atributos
$linkDom.setAttribute("target","_blank");
//Con este le decimos que la segunda ventana no tiene relacion con la primera
$linkDom.setAttribute("rel","noopener");

$linkDom.setAttribute("href","https://www.google.com/")
console.log($linkDom.hasAttribute('rel'))
$linkDom.removeAttribute('rel')
console.log($linkDom.hasAttribute('rel'))

//Data-attributes

console.log($linkDom.getAttribute('data-description')) //Obtiene el valor del data creado, los datas antes de ser creados lo idea es que anteponga del nombre la palabra data-name
console.log($linkDom.dataset) //Lista los attributos data de objeto ejm: id:'1', description: "Document Object Model"
console.log($linkDom.dataset.id)
$linkDom.setAttribute('data-description','Modelo del Objeto del Documento')
console.log($linkDom.getAttribute('data-description'))//De estas 2 maneras podemos cambiar el valor de un atributo
console.log($linkDom.dataset.description ='Suscribite a mi curso web')


console.clear();

//DOM: Estilos y Variables CSS

console.log($linkDom.style)//Aunque se las 2 maneras se puede acceder a los atributos los dos imprimen cosas diferentes, este imprime un objeto con todosd los valores css
console.log($linkDom.getAttribute('style'))//Este imrpime solo los valores actuales en forma de texto.
console.log($linkDom.style.color)
//PAra acceder a las propiedades css desde javascript se utiliza el formato cammelCase ya que si usamos el guion para separar en javascript puede ser una resta
$linkDom.style.backgroundColor ='red'
console.log(window.getComputedStyle($linkDom))//Esta es otra forma poco convencional de acceder a las propiedades css imprime un objeto ordenado alfabeticamente
console.log(getComputedStyle($linkDom).getPropertyValue('color'))

//Formas de agregar mas propiedades
$linkDom.style.setProperty('text-decoration','none')
$linkDom.style.setProperty('display','block')
$linkDom.style.width ='50%'
$linkDom.style.textAlign ='center'
$linkDom.style.marginLeft ='auto'
$linkDom.style.marginRight ='auto'
$linkDom.style.padding ='1rem'
$linkDom.style.borderRadius ='.5rem'

console.log($linkDom.getAttribute('style'))
console.log(window.getComputedStyle($linkDom))

console.clear();

//Variables css Custom Properties CSS

/*const $html = document.documentElement,$body = document.body; // $x Variables que almacenan una referencia en el dom.
//Todas las variables css empiezan con  "--""  ejemplo --dark-color
let varDarkColor = getComputedStyle($html).getPropertyValue('--dark-color'),varYelowColor = getComputedStyle($html).getPropertyValue('--yelow-color')

console.log(varDarkColor,varYelowColor)

$body.style.background = varDarkColor;
$body.style.color = varYelowColor;

$html.style.setProperty('--dark-color','#000');
varDarkColor = getComputedStyle($html).getPropertyValue('--dark-color');


//$body.style.background = varDarkColor

console.clear();*/

// Clases CSS
/*const $card = document.querySelector('.card');
console.log($card)
console.log($card.className)
console.log($card.classList)
console.log($card.classList.contains("rotate-45"))
$card.classList.add("rotate-45")

console.log($card.classList.contains("rotate-45"))
console.log($card.className)
console.log($card.classList)

$card.classList.remove("rotate-45")
console.log($card.className)
$card.classList.toggle("rotate-45") // si no tiene la clase se la agrega
$card.classList.replace("rotate-45","rotate-135")
$card.classList.add("sepia","opacity-80")// agrega las clases separadas por ,
$card.classList.remove("sepia","opacity-80",'rotate-135') // Elimina las clases separadas por ,
console.clear();*/

// DOM: Texto y HTML

const $wathlsDOM = document.getElementById('que-es');

let text = `
    <p>
    El Modelo de Objetos del Documento (<b><i>DOM - Document Object Model </i></b>) es un                    
    API para documentos HTML y XML.
    </p>
    <p>
    Éste provée una representación estructural del documento, permitiendo modificar su contenido y presentación visual mediante código JS.
    </p>
    <p>
        <mark> El DOM no es parte de la especificación de JavaScript, es una API para los navegadores.</mark>
    </p>
`

//InnerText y InnerContent sirven para agregar contenido de texto a una etiqueta html la diferencia es que InnerText se creo inicialmente para internet explorer
//InnerContent es la version estandar
//$wathlsDOM.innerText = text; // Imprime en modo text sin reconocer las etiquetas HTML dentro de la variable template
$wathlsDOM.textContent = text; // Imprime en modo text sin reconocer las etiquetas HTML y tabulaciones  dentro de la variable template
$wathlsDOM.innerHTML = text; // Imprime reconociendo las etiquetas HTML y tabulaciones  dentro de la variable template
$wathlsDOM.outerHTML = text; //Lo que hace es que si el contenido de la var text remplaza las etiquetas de la variable DOM  lo cambia



// 67. DOM Traversing: Recorriendo el DOM

/*const $cards = document.querySelector('.cards')
console.log($cards.children)//Imprime todas las card dentro de cards
console.log($cards.children[3])//Imprime un hijo seleccionado
console.log($cards.childNodes)//Imprime todos los nodos o etiquetas del elemento cards

console.log($cards.parentElement)//Imprime el padre de cards que seria body
console.log($cards.children[3].parentElement)//Imprime el padre de card que seria cards
console.log($cards.firstElementChild)//Imprime el primer elemento hijo de las cards
console.log($cards.lastElementChild)//Imprime el ultimo elemento hijo de las cards

console.log($cards.previousElementSibling)//Imprime el primer elemento Que esta antes de las cards
console.log($cards.nextElementSibling)//Imprime el elemento que esta despues de las cards
console.log($cards.closest('a'))//Obtiene el primer padre cernano hacia arriba 
console.log($cards.closest('body'))
console.log($cards.children[0].closest('section'))*/


//68. DOM: Creando Elementos y Fragmentos 
/*const $cards = document.querySelector('.cards');
const $figure = document.createElement('figure'),$img = document.createElement('img'),$figcaption = document.createElement('figcaption'),$figcaptionText = document.createTextNode('Animals')
const $figure2 = document.createElement('figure');
$img.src ="https://placeimg.com/200/200/animals"
$img.alt = 'Animals'
$figcaption.appendChild($figcaptionText)
$figure.appendChild($img)
$figure.appendChild($figcaption)
$figure.classList.toggle('card')
$cards.appendChild($figure)

$figure2.innerHTML = `
    <img src="https://placeimg.com/200/200/people" alt="People">
    <figcaption>People</figcaption>
`;
$cards.appendChild($figure2).classList.toggle('card');

const estaciones = ['invierno','verano','otoño','primavera'],$ul = document.createElement('ul');


document.write('<h3>Estaciones del año</h3>');
document.body.appendChild($ul)
estaciones.forEach(li => {
    const $li = document.createElement('li');
    $li.textContent = li
    $ul.appendChild($li)
});

document.write('<h3>Continentes del Mundo</h3>');
const continents = ['Africa','America','Asia','Oceania'];
const $ul2 = document.createElement('ul');
document.body.appendChild($ul2)
continents.forEach(el=>{
    $ul2.innerHTML +=  `<li>${el}</li>`
})

//Estas 2 formas anteriores de insertar elementos no es la mas adecuada ya que si tenemos miles de datos vamos a estar consumiendo mucha meomoria usando el DOM


document.write('<h3>Meses del año</h3>');
const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Novimebre','Diciembre'];
const $ul3 = document.createElement('ul'),$fragment = document.createDocumentFragment();
meses.forEach(el=>{
    console.log(el)
    const $li = document.createElement('li');
    $li.textContent = el;
    $fragment.appendChild($li)
})

$ul3.appendChild($fragment)
document.body.appendChild($ul3)*/


//69. DOM: Templates HTML
//Los templates son modelos o contenidos a seguir por ejemplo en POO son como una clase que encapsulara  todo el contenido, en este caso encapsulara contenido html
/*const $cardss = document.querySelector('.cards'),$template = document.getElementById('template-card').content,$fragments = document.createDocumentFragment(),
cardContent = [
    {
        title:'Tecnologia',
        img:'https://placeimg.com/200/200/tech'
    },
    {
        title:'Animales',
        img:'https://placeimg.com/200/200/animals'
    },
    {
        title:'Gente',
        img:'https://placeimg.com/200/200/people'
    },
    {
        title:'Arquitectura',
        img:'https://placeimg.com/200/200/arch'
    },
    {
        title:'Naturaleza',
        img:'https://placeimg.com/200/200/nature'
    }
];

cardContent.forEach(el=>{
    $template.querySelector('img').setAttribute('src',el.img)
    $template.querySelector('img').setAttribute('alt',el.title)
    $template.querySelector('figcaption').textContent= el.title;

    let $clone = document.importNode($template,true)//Si le pasamos true copia todo el contenido si le pasamos false solo copia la etiqueta template;

    $fragments.appendChild($clone)
});
$cardss.appendChild($fragments)*/


//70. DOM: Modificando Elementos (Old Style)

/*const $cards = document.querySelector('.cards'), $newCard = document.createElement('figure');
$newCard.classList.toggle('card')
$newCard.innerHTML = `
    <img src="https://placeimg.com/200/200/any" alt="Any">
    <figcaption>Any</figcaption>
`;
$cloneCards = $cards.cloneNode(true);*/
//$cards.replaceChild($newCard,$cards.children[1]) Reemplaza un nodo/elemento en x posicion

//$cards.insertBefore($newCard,$cards.firstElementChild) Inserta un nodo/elemento antes de x posicion

//$cards.removeChild($cards.lastElementChild) Elimina un nodo/elemento en x posicion

//document.body.appendChild($cloneCards) Clona un elemento y lo inserta en el dom


//71. DOM: Modificando Elementos (Cool Style) 

/*
  .insertAdjacent...
  .insertAdjacentElement(position,el)
  .insertAdjacentHTML(position,html)
  .insertAdjacentText(position,text)

  Posiciones :

  beforebegin(hermano Anterior)
  afterbegin(primer hijo)
  beforeend(ultimo hijo)
  afterend( hermano siguiente)
*/

/*const $cards = document.querySelector('.cards'), $newCard = document.createElement('figure');
$contentCard = `
    <img src="https://placeimg.com/200/200/any" alt="Any">
    <figcaption></figcaption>
`;
$newCard.classList.add('card')
//$cards.insertAdjacentElement('beforebegin',$newCard) // Inserta antes del elemento anterior
//$cards.insertAdjacentElement('afterbegin',$newCard) //Inserta antes del primer hijo
//$cards.insertAdjacentElement('beforeend',$newCard) // Inserta despues del ultimo hijo
//$cards.insertAdjacentElement('afterend',$newCard) //Inserta despues del ultimo elemento
$newCard.insertAdjacentHTML('beforeend',$contentCard);
$newCard.querySelector('figcaption').insertAdjacentText('afterbegin','Any')
//$cards.insertAdjacentElement("afterbegin",$newCard)
//cards.prepend($newCard);
//$cards.append($newCard);
//$cards.before($newCard);
//$cards.after($newCard);*/


//72. DOM: Manejadores de Eventos

function holaMundo(){
    alert('Hola mundo')
    console.log(event)
}
const $eventoSemantico = document.getElementById('evento-semantico')
$eventoSemantico.onclick = holaMundo
$eventoSemantico.onclick = (e)=>{
    alert('Hola mundo evento semantico')
    console.log(e)
    console.log(event)
}

