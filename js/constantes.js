export const PI = Math.PI

export const USUARIO ='Diego';

const PASSWORD ='2020*'

//Exporta por defaul el metodo saludar y no pueden haber 2 defaul solo permite uno por script
//Otra limitacion es que no puedo exportar una variable defaul en la misma linea ejemplo : export default let costo = 0; esto genera error
//Pero si yo declaro la variable costo en la linea anterior si lo puedo hacer export default costo = 0;
//Las unicas que podemos exportar directamente son las funciones y las clases export default function  y export default class

export default  function saludar(){
    console.log("Hola mundo +ES6")
}

export  class Saludar{
    constructor(){
        console.log("mensaje desde constructor de clase")
    }
}