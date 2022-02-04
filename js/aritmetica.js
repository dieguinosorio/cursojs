function sumar(a,b){
    return a+b;
}

function restar(a,b){
    return a-b;
}

function multiplicar(a,b){
    return a*b;
}

function dividir(a,b){
    return a / b
}
//Exportamos un objeto literal, no tenemos que declarar el nombre de la propiedad
export const calculadora = {
    sumar,
    restar,
    multiplicar,
    dividir
}