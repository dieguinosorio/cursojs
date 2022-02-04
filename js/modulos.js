import  saludar,{Saludar, PI,USUARIO } from './constantes.js'//Aqui estamos haciendo destructuring dentro de {} con las variables que vienen de modulo constantes
import { calculadora as cal } from './aritmetica.js';//Una forma de hacer un alias

console.log(PI,USUARIO)
console.log(cal.sumar(5,8))
console.log(cal.restar(5,8))
console.log(cal.multiplicar(5,8))
console.log(cal.dividir(5,8))
//Como el metodo esta por default en constantes ya solo lo invocamos

let saludo = new Saludar();
saludo
saludar()