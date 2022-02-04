<?php

prado::using("Application.pages.utilidades.administrativas.regeneracion.RegenerarCantidades");
prado::using("Application.pages.herramientas.compromisos.AgregarCompromiso");

/**
 * La clase contiene variedad de funciones que podran ser accedidas por cualquiera de
 * las clases del proyecto.
 * */
class funciones {

    /**
     * Despliega un mensajfe de informacion.
     * @param Text enviado desde la funcion de una clase externa.
     * @param Tipo Es el tipo de mensaje que se desea mostrar
     * 1. Mensaje Letra Negra
     * 2. Mensaje Letra Roja
     * */
    public static function Mensaje($msg, $Tipo, $objVista) {
        switch ($Tipo) {
            case 1:
                $objVista->FraMensaje->Visible = true;
                $objVista->LblMensaje->Text = $msg;
                break;

            case 2:
                $objVista->FraMensaje->Visible = true;
                $objVista->LblMensaje->Text = "<font color='red'>$msg</font>";
                break;
        }
    }

    /**
     * Redirecciona el navegador a la pagina enviada como parametro.
     * 
     * @param string $strUrl url a donde se piensa redireccionar
     * @param object $objVista paso de la presentacion para utilizar los objetos de esta 
     * */
    public static function IrA($strUrl, $objVista) {
        $objVista->Response->redirect($strUrl);
    }

    /**
     * Redirecciona el navegador a la pagina de información de restriccion de acceso.
     * @param objeto $objVista La vista actual.
     * */
    public static function AccesoDenegado($objVista) {
        funciones::IrA('?page=seguridad.Acceso', $objVista);
    }

    /**
     * Muestra un mensaje de advertencia cuando el usuario no tiene  permisos para ejecutar una determinada acccion
     */
    public static function AccionNoPermitida($objVista) {
        funciones::Mensaje("El usuario no tiene permisos para ejecutar esta acción.", 2, $objVista);
    }

    /**
     * Comprueba si un movimiento determinado tiene afectadas las cantidades.
     * @param IdMovimiento
     * */
    public static function ComprobarAfectada($IdMovimiento) {
        $MovDet = new MovimientosDetRecord;
        $sql = "Select IdMovimientoDet, CantAfectada from movimientos_det where cantafectada>0 and idmovimiento=" . $IdMovimiento;
        $MovDet = MovimientosDetRecord::finder()->findAllBySql($sql);
        if (count($MovDet) >= 1)
            return true;
        else
            return false;
    }

    public static function ComprobarExistenciaDisp($IdMovimiento, $AfectaInventarios, $Comodato) {
        $Existencia = true;
        if ($AfectaInventarios == 1) {
            $sql = "SELECT IdMovimientoDet, IdMovimiento, Id_Item, Lote, Bodega, Cantidad, Operacion FROM movimientos_det WHERE IdMovimiento=" . $IdMovimiento;
            $MovDet = MovimientosDetRecord::finder()->findAllBySql($sql);
            $NroReg = count($MovDet);
            $i = 0;
            $arItem = new ItemRecord();
            while ($i < $NroReg && $Existencia == true) {
                $arItem = ItemRecord::finder()->FindByPk($MovDet[$i]->Id_Item);
                if ($arItem->AfectaInventario == 1) {
                    if ($Comodato == 0) {
                        $Lotes = LotesRecord::finder()->findByPk($MovDet[$i]->Id_Item, $MovDet[$i]->Lote, $MovDet[$i]->Bodega);
                    } else {
                        $Lotes = LotesComodatosRecord::finder()->findByPk($MovDet[$i]->Id_Item, $MovDet[$i]->Lote, $MovDet[$i]->Bodega);
                    }
                    if (($MovDet[$i]->Cantidad * $MovDet[$i]->Operacion) > $Lotes->Existencia || ($MovDet[$i]->Cantidad * $MovDet[$i]->Operacion) > $Lotes->Disponible) {
                        $Existencia = false;
                    }
                }
                $i++;
            }
        }
        return $Existencia;
    }

    public static function ComprobarCantRegistros($IdMovimiento, $Tipo) {
        $movimiento = MovimientosRecord::finder()->FindByPk($IdMovimiento);
        $CantReg = [];
        $CantRegCant = [];
        if ($Tipo == 1) {
            $MovDet = new MovimientosDetRecord;
            $MovDet = MovimientosDetRecord::finder()->findAllBySql("Select IdMovimientoDet from movimientos_det where idmovimiento=" . $IdMovimiento);
            $CantReg = count($MovDet);
        } elseif ($Tipo == 2) {
            $MovDet = new MovimientosDetRecord;
            $MovDet = MovimientosDetRecord::finder()->findAllBySql("Select IdMovimientoDet, Cantidad from movimientos_det where cantidad<=0 and idmovimiento=" . $IdMovimiento);
            $CantRegCant = count($MovDet);
        }
        if ($Tipo == 2) {
            if ($CantRegCant > 0 && $movimiento->IdConcepto == 179) {
                return true;
            } else if ($CantRegCant > 0) {
                return false;
            } else {
                return true;
            }
        } else {
            return $CantReg;
        }
    }

    /**
     * Registra el afectado del documento enlace.
     * @param IdMovimiento
     * @Tipo  1 es registrar, 2 eliminar
     * @param type $IdMovimiento
     * @param type $Tipo
     * @param type $objVista
     * @param type $RegistrarAfectada
     * @param type $IsMovDet
     * @return boolean
     * @param type $Op es true si se esta actualizando el estado para el detalle es false.
     */
    public static function RegistrarAfectada($IdMovimiento, $Tipo, $objVista, $RegistrarAfectada, $IsMovDet = false, $Op = true) {
        $TipoMov = "IdMovimiento";
        if ($IsMovDet == true) {
            $TipoMov = "IdMovimientoDet";
        }
        $MovDet = new MovimientosDetRecord();
        $sql = "Select * from movimientos_det where $TipoMov=" . $IdMovimiento;
        $MovDet = MovimientosDetRecord::finder()->FindAllBySql($sql);
        $Afectada = false;
        $Mensaje = '';
        if (count($MovDet) > 0) {

            if ($Tipo == 1) {

                foreach ($MovDet as $MovDet) {
                    if ($Op == true && $MovDet->Estado != "AUTORIZADO" || $Op == false) {//Valida para que no vuelva a registrar un detalle que ya se autorizo.
                        $MovDetDatos = new MovimientosDetRecord();
                        $MovDetDatos = MovimientosDetRecord::finder()->with_MovDet()->FindByPk($MovDet->IdMovimientoDet);
                        $Movimiento = new MovimientosRecord();
                        $Movimiento = MovimientosRecord::finder()->with_Concepto()->with_DocumentoMov()->FindByPK($MovDet->IdMovimiento);
                        if ((($MovDetDatos->IdDocumento == 34 || $MovDetDatos->IdDocumento == 72) && $MovDetDatos->Operacion <> 0) || funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto) || ($MovDetDatos->MovDet && $MovDetDatos->MovDet->IdDocumento == 75) || $RegistrarAfectada == false || $MovDetDatos->IdDocumento == 10) {
                            $Afectada = true;
                            $MovDetEnlace = null;
                            if ($Movimiento->IdConcepto == 179 && $Movimiento->IdDocumento == 87) {
                                $Cantidad = $MovDetDatos->Cantidad;
                                $MovDetEnlace = new MovimientosDetRecord();
                                $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                                if ($Movimiento->IdConcepto > 0) {
                                    $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $Cantidad;
                                    //Validamos las notas credito proveedor que afecte cantidad como costo.
                                    if ($MovDetEnlace->IdDocumento == 85) {
                                        $DifCostoRec = ($MovDetDatos->CantidadReconocidaNC * $MovDetDatos->DiferenciaCostoNC);
                                        $DifSolicitada = ($MovDetDatos->CantSolicitud * $MovDetDatos->Costo);
                                        $Diferencia = $DifCostoRec - $DifSolicitada;
                                        if ($Diferencia >= -2000 && $Diferencia <= 2000 && $MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                                            $MovDetEnlace->Estado = 'CERRADO';
                                        }
                                    } else if ($MovDetEnlace->CantAfectada <= $MovDetEnlace->Cantidad) {
                                        $MovDetEnlace->Estado = 'CERRADO';
                                    }
                                    $MovDetEnlace->Comentarios = $MovDetDatos->Comentarios;
                                    $MovDetEnlace->save();
                                }
                            } else if (funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto) && $Movimiento->DocumentoMov->AfectaCantRef) {
                                $MovDetEnlace = new MovimientosDetRecord();
                                $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $MovDetDatos->Cantidad;
                                if ($Movimiento->IdConcepto == 177) {
                                    $diferenciaTotal = $MovDetEnlace->SubTotal - $MovDetDatos->SubTotal;
                                    if ($diferenciaTotal >= -2000 && $diferenciaTotal <= 2000 && $MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                                        $MovDetEnlace->Estado = 'CERRADO';
                                    }
                                } else if ($MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                                    $MovDetEnlace->Estado = 'CERRADO';
                                }
                                $MovDetEnlace->save();
                            } else if ($MovDetDatos->MovDet && $MovDetDatos->MovDet->IdDocumento == 75 && $MovDetDatos->IdDocumento != 19) {
                                $MovDetEnlace = new MovimientosDetRecord();
                                $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $MovDetDatos->Cantidad;
                                if ($MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                                    $MovDetEnlace->Estado = 'CERRADO';
                                }
                                $MovDetEnlace->save();
                            }
                            if ($MovDetEnlace) {
                                $strSql = "SELECT IdMovimientoDet FROM movimientos_det WHERE Estado <> 'CERRADO' AND IdMovimiento = " . $MovDetEnlace->IdMovimiento;
                                $arMovimientos = MovimientosDetRecord::finder()->findAllBySql($strSql);
                                if (count($arMovimientos) == 0) {
                                    $MovDatos = new MovimientosRecord();
                                    $MovDatos = MovimientosRecord::finder()->FindByPk($MovDetEnlace->IdMovimiento);
                                    $MovDatos->Estado = 'CERRADA';
                                    $MovDatos->save();
                                }
                            }
                        } else {
                            if ($MovDetDatos->MovDet != NULL || $MovDetDatos->MovDet != 0) {

                                if (($MovDetDatos->MovDet->Cantidad - $MovDetDatos->MovDet->CantAfectada) != 0) {
                                    $Cantidad = $MovDetDatos->Cantidad;
                                    if ($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion == 1) {
                                        $Cantidad = $MovDetDatos->MovDet->Cantidad;
                                    }

                                    if (($Cantidad) <= ($MovDetDatos->MovDet->Cantidad - $MovDetDatos->MovDet->CantAfectada) || $Movimiento->IdConcepto == 179) {


                                        $MovDetEnlace = new MovimientosDetRecord();
                                        $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                                        if ($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion != 1 || $Movimiento->IdConcepto == '') {
                                            $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $Cantidad;
                                            //Validamos las notas credito proveedor que afecte cantidad como costo.
                                            if ($MovDetEnlace->IdDocumento == 87 && $Movimiento->IdConcepto == 179) {
                                                if ($MovDetEnlace->Costo == $MovDetDatos->Costo && $MovDetEnlace->CantAfectada == $MovDetEnlace->Cantidad) {
                                                    $MovDetEnlace->Estado = 'CERRADO';
                                                }
                                            } else if ($MovDetEnlace->CantAfectada == $MovDetEnlace->Cantidad) {
                                                $MovDetEnlace->Estado = 'CERRADO';
                                            }
                                            $MovDetEnlace->save();
                                        }
                                        $Afectada = true;

                                        if ($MovDet->IdDocumento == 1) {
                                            $Item = ItemRecord::finder()->FindByPk($MovDet->Id_Item);
                                            $Item->CantOC = $Item->CantOC - $Cantidad;
                                            $Item->save();
                                        }
                                    } else {
                                        if ($MovDetEnlace->IdDocumento == 75) {
                                            
                                        }
                                        $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no esta disponible, ya fue enlazado anteriormente, revise por favor, o valide que la cantidad no sea mayor que la del documento inicial';
                                        $Afectada = false;
                                    }
                                } else {
                                    $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no esta disponible, ya fue enlazado anteriormente, revise por favor.';
                                    $Afectada = false;
                                }

                                if ($Afectada == true) {
                                    MovimientosRecord::CerrarAutoMov($MovDetEnlace->IdMovimiento);
                                }
                            } else {
                                $Afectada = true;
                            }
                        }
                    } else {
                        $Afectada = true;
                    }
                }
            }
            if ($Tipo == 2) {

                foreach ($MovDet as $MovDet) {

                    $MovDetDatos = new MovimientosDetRecord();
                    $MovDetDatos = MovimientosDetRecord::finder()->with_MovDet()->FindByPk($MovDet->IdMovimientoDet);
                    $Movimiento = new MovimientosRecord();
                    $Movimiento = MovimientosRecord::finder()->with_Concepto()->FindByPK($MovDet->IdMovimiento);

                    if ((($MovDetDatos->IdDocumento == 34 || $MovDetDatos->IdDocumento == 72) && $MovDetDatos->Operacion <> 0) || $MovDetDatos->IdDocumento == 36 || $MovDetDatos->TpDocumento == 33 || $MovDetDatos->IdDocumento == 10 || funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto)) {
                        $Afectada = true;
                        $Cantidad = $MovDetDatos->Cantidad;
                        $MovDetEnlace = new MovimientosDetRecord();
                        $MovDetEnlace = $MovDetDatos->MovDet ? MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet) : null;

                        if ($Movimiento->IdConcepto == 179 || $Movimiento->IdConcepto == 185) {

                            if ($Movimiento->IdConcepto == 185 && $Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion != 1 || $Movimiento->IdConcepto == '') {
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $Cantidad;
                                $MovDetEnlace->Estado = 'AUTORIZADO';
                                $MovDetEnlace->save();
                            } elseif ($Movimiento->IdConcepto == 179 && $MovDetEnlace->TpDocumento != 5) {
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $Cantidad;
                                $MovDetEnlace->Estado = 'AUTORIZADO';
                                $MovDetEnlace->save();
                            }
                        } else if (funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto) && $MovDetEnlace) {
                            $MovEnl = MovimientosRecord::finder()->with_DocumentoMov()->FindByPK($MovDetEnlace->IdMovimiento);
                            if ($MovEnl->DocumentoMov && $MovEnl->DocumentoMov->AfectaCantRef && $MovDetEnlace) {
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $Cantidad;
                                $MovDetEnlace->Estado = 'AUTORIZADO';
                                $MovDetEnlace->save();
                            }
                        }
                        $MovDetEnlace ? MovimientosRecord::CerrarAutoMov($MovDetEnlace->IdMovimiento) : null;
                    } else {

                        if ($MovDetDatos->MovDet != NULL || $MovDetDatos->MovDet != 0) {
                            if ($MovDetDatos->MovDet->CantAfectada > 0 || $MovDetDatos->TpDocumento == 33) {

                                if ($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion == 1) {
                                    $MovDetEnlace = new MovimientosDetRecord();
                                    $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);

                                    $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $MovDetEnlace->Cantidad;
                                    $MovDetEnlace->Estado = 'AUTORIZADO';
                                    $MovDetEnlace->save();
                                    $Afectada = true;
                                } else if (($MovDetDatos->Cantidad) <= ($MovDetDatos->MovDet->CantAfectada)) {

                                    $MovDetEnlace = new MovimientosDetRecord();
                                    $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);

                                    $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $MovDetDatos->Cantidad;
                                    $MovDetEnlace->Estado = 'AUTORIZADO';
                                    $MovDetEnlace->save();
                                    $Afectada = true;

                                    if ($MovDet->IdDocumento == 1) {
                                        $Item = ItemRecord::finder()->FindByPk($MovDet->Id_Item);
                                        $Item->CantOC = $Item->CantOC + $MovDetDatos->Cantidad;
                                        $Item->save();
                                    }
                                } else {
                                    $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no esta se puede devolver al pedido, ya fue enlazado anteriormente, revise por favor.';
                                    $Afectada = false;
                                }
                            } else {
                                $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no se puede devolver al pedido, ya fue enlazado anteriormente, revise por favor.';
                                $Afectada = false;
                            }



                            if ($Afectada == true) {
                                MovimientosRecord::CerrarAutoMov($MovDetDatos->MovDet->IdMovimiento);
                            }
                        } else {
                            $Afectada = true;
                        }
                    }
                }
            }



            if ($Afectada == true) {
                return true;
            } else {
                funciones::Mensaje($Mensaje, 2, $objVista);
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Actualiza el estado de un documento y ejecuta las acciones de inventario
     * Acciones
     * 1 - Anular documento
     * 2 - Autorizar
     * 3 - Anular
     * 4 - Cerrar
     * 
     * @param integer $IdMovimiento Id del movimiento a trabajar
     * @param integer $Tipo indica el tipo de accion 
     * @param object $objVista paso de la presentacion para utilizar los objetos de esta  
     */
    public static function ActualizarEstadoAnterior($IdMovimiento, $Tipo, $objVista, $IdAccion = 0, $Comentarios = '', $RegistrarAfectada = true) {

        $strEstadoTipo = '';
        $Afectada = false;
        $Movimientos = MovimientosRecord::finder()->with_Concepto()->findByPk($IdMovimiento);
        if (funciones::ValidarGeneracionRequerimiento($IdMovimiento, false) == false) {
            switch ($Tipo) {
                case 1:
                    $strEstadoTipo = 'ANULADA';
                    $IdAccion = 1;
                    break;

                case 2:
                    $strEstadoTipo = 'AUTORIZADA';
                    $IdAccion = 2;
                    break;

                case 3:
                    $strEstadoTipo = 'DIGITADA';
                    $IdAccion = 3;
                    break;

                case 4:
                    $strEstadoTipo = 'CERRADA';
                    $IdAccion = 4;
                    break;

                case 5:
                    $strEstadoTipo = 'AUTORIZADA';
                    $IdAccion = 5;
                    break;
            }

            //Validando que el estado al que se actualizar no sea el mismo actual del doc. 
            if ($Movimientos->Estado != $strEstadoTipo) {

                if (($Movimientos->IdDocumento == 3 || $Movimientos->IdDocumento == 11 || $Movimientos->IdDocumento == 12) && ($Movimientos->Alistando == 1)) {
                    funciones::Mensaje("No se puede cambiar el estado de un documento que esta siendo alistado.", 2, $objVista);
                    return false;
                }

                $Documentos = DocumentosRecord::finder()->findByPk($Movimientos->IdDocumento);
                $TpDocumentos = TpDocumentosRecord::finder()->findByPk($Documentos->Tp);
                //1-Anular 3-Desautorizar
                if ($Tipo == 1 || $Tipo == 3) {
                    if (funciones::ComprobarAfectada($objVista->LblIdMovimiento->Text) == 0 || $Movimientos->TpDocumento == 2) {
                        if (funciones::ComprobarExistenciaDisp($IdMovimiento, $Documentos->Operacion, $Documentos->DocComodato) == true || (($Documentos->IdDocumento == 36 || $Documentos->IdDocumento == 84) && ($Movimientos->IdConcepto > 0 && $Movimientos->Concepto->Opcion != 0 ))) {
//                    if ($Movimientos->Estado == "AUTORIZADA" || $Movimientos->IdDocumento == 5 || $Movimientos->IdDocumento == 15 || $Movimientos->IdDocumento == 1) {
                            if ($Tipo == 1) {
                                if (funciones::ComprobarPermiso($Movimientos->IdDocumento, 4, $objVista) == true) {
                                    if (MovimientosRecord::Impresiones($IdMovimiento) == 0 && ($Movimientos->TpDocumento == 5 || $Movimientos->TpDocumento == 7)) {
                                        funciones::Mensaje("No se puede anular un documento que no ha sido impreso.", 2, $objVista);
                                        return false;
                                    }
                                    $Movimientos->Estado = "ANULADA";
                                    $Movimientos->Anulado = 1;
                                    funciones::DevolverCantidades($IdMovimiento, $Documentos->AfectaCantRef, $Documentos->Tp);
                                    funciones::ActualizarEstadoDetAll($IdMovimiento, "ANULADO", -1, $Movimientos->IdDocumento, $objVista);

                                    $Movimientos->VrIva = 0;
                                    $Movimientos->VrDcto = 0;
                                    $Movimientos->VrOtros = 0;
                                    $Movimientos->VrRetencion = 0;
                                    $Movimientos->SubTotal = 0;
                                    $Movimientos->VrFletes = 0;
                                    $Movimientos->VrRteIva = 0;
                                    $Movimientos->Total = 0;
                                    $Movimientos->Costo = 0;
                                    $Movimientos->VrRteFuente = 0;
                                    $Movimientos->VrRteCree = 0;

                                    //Colocar las retenciones y otros valores en cero
                                    $sql = "UPDATE movimientos_otros SET Debito=0, Credito=0 Where IdMovimiento=" . $IdMovimiento;
                                    $command = Movimientos_OtrosRecord::finder()->getDbConnection()->createCommand($sql);
                                    $command->execute();

                                    $sql = "UPDATE movimientos_retenciones SET BaseRetencion=0, VrRetencion=0 Where IdMovimiento=" . $IdMovimiento;
                                    $command = Movimientos_RetencionesRecord::finder()->getDbConnection()->createCommand($sql);
                                    $command->execute();

                                    //Volver a abrir los documentos enlace
                                    $MovDetLib = MovimientosDetRecord::finder()->FindAllBy_IdMovimiento($IdMovimiento);
                                    for ($i = 0; $i < count($MovDetLib); $i++) {
                                        if ($MovDetLib[$i]->Enlace != NULL)
                                            MovimientosDetRecord::AbrirAutoDet($MovDetLib[$i]->Enlace);
                                    }

                                    for ($i = 0; $i < count($MovDetLib); $i++) {
                                        if ($MovDetLib[$i]->IdDocumento == 1) {
                                            $Item = ItemRecord::finder()->FindByPk($MovDetLib[$i]->Id_Item);
                                            $Item->CantOC = $Item->CantOC + $MovDetLib[$i]->Cantidad;
                                            $Item->save();
                                        }
                                    }

                                    $sql = "UPDATE movimientos_det SET Cantidad=0, Total=0, Precio=0, Costo=0, CostoPromedio=0, SubTotal=0, PorIva=0, CantDescuento=0, CantDescuento=0, Enlace=NULL WHERE IdMovimiento=" . $IdMovimiento;
                                    $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                                    $command->execute();

                                    funciones::Liquidar($IdMovimiento);
                                    $Afectada = true;
                                }
                            } elseif ($Tipo == 3) {

                                $ContItem = 1; //Validamos que si no tiene un productos lo deje desautorizar.
                                if ($Movimientos->IdDocumento == 3) {
                                    $MovDet = MovimientosDetRecord::finder()->FindAllBy_IdMovimiento($Movimientos->IdMovimiento);
                                    $ContItem = count($MovDet);
                                }
                                if (funciones::ComprobarPermiso($Movimientos->IdDocumento, 6, $objVista) == true || $ContItem == 0) {
                                    if ($Movimientos->Impresion < 1 || funciones::IsAdmin($objVista->User->Name) == true || $Movimientos->IdDocumento == 8 || (funciones::PermisosConsultas($objVista->User->Name, 40) == true && $Movimientos->IdDocumento != 1) || funciones::ValidarSegundaAutorizacion(20, $IdMovimiento, '', $objVista) == true || ($Movimientos->TpDocumento == 33)) {
                                        if (funciones::RegistrarAfectada($IdMovimiento, 2, $objVista, $RegistrarAfectada) == true) {
                                            $Movimientos->Estado = "DIGITADA";
                                            $Movimientos->Autorizado = 0;
                                            $Movimientos->IdAutoriza = "";
                                            funciones::ActualizarEstadoDetAll($IdMovimiento, "DIGITADO", -1, $Movimientos->IdDocumento, $objVista);
                                            $Afectada = true;
                                        }
                                    } else {
                                        funciones::Mensaje("No se pueden desautorizar documentos impresos", 2, $objVista);
                                        $Afectada = false;
                                    }
                                }
                            }
//                    } else {
//                        funciones::Mensaje('El documento debe estar Autorizado para anular', 1, $objVista);
//                    }
                        } else {
                            Funciones::Mensaje('Ya se han utilizardo cantidades de este documento, el disponible o la existencia son menores, por lo tanto, no se puede desautorizar', 1, $objVista);
                        }
                    } else {
                        funciones::Mensaje('Este documento tiene cantidades afectadas y no se puede Anular o desautorizar asi', 1, $objVista);
                    }
                }

                //2 -Autorizar
                if ($Tipo == 2) {

                    // Comprobar permiso.
                    if (funciones::ComprobarPermiso($Movimientos->IdDocumento, 5, $objVista) == true || (($Movimientos->IdDocumento == 5 || $Movimientos->IdDocumento == 15 || $Movimientos->IdDocumento == 68 || $Movimientos->IdDocumento == 31 || $Movimientos->IdDocumento == 36 || $Movimientos->IdDocumento == 84) && (funciones::ValidarSegundaAutorizacion(38, $Movimientos->IdMovimiento, '', $objVista) == true) || (funciones::ValidarSegundaAutorizacion(38, $Movimientos->IdMovimiento, '', $objVista) == true) || funciones::ValidarSegundaAutorizacion(16, $Movimientos->IdMovimiento, '', $objVista) == true)) {
                        if (funciones::ValidarCantidadesEnlace($IdMovimiento, $objVista) != false || ($Movimientos->IdConcepto > 0 && $Movimientos->Concepto->Opcion > 0)) {
                            if ($Movimientos->Total >= 0) {
                                // Comprueba que no tenga cantidades en cero(0).
                                if ((funciones::ComprobarCantRegistros($IdMovimiento, 1) > 0 && funciones::ComprobarCantRegistros($IdMovimiento, 2) <= 0) || $Movimientos->IdConcepto == 179) {
                                    if ($Movimientos->Estado == "DIGITADA") {
                                        funciones::GuardarCantAjustada($IdMovimiento);
                                        $ValiReg = funciones::RegistrarAfectada($IdMovimiento, 1, $objVista, $RegistrarAfectada);
                                        $Vali = funciones::ActualizarEstadoDetAll($IdMovimiento, "AUTORIZADO", 1, $Movimientos->IdDocumento, $objVista);
                                        if ($Vali == true && $ValiReg == true) {
                                            $Movimientos->Estado = "AUTORIZADA";
                                            $Movimientos->Autorizado = 1;
                                            $Movimientos->IdAutoriza = $objVista->User->Name;
                                            $Movimientos->FhAutoriza = date('y-m-d H:i:s');
                                            //Para que las facturas sin imprimir queden con la fecha de autorizacion
                                            if ($Movimientos->IdDocumento == 3)
                                                $Movimientos->Fecha = $Movimientos->FhAutoriza;

                                            if ($Documentos->Operacion == 1) {
                                                $Movimientos->FhAutoriza = $Movimientos->Fecha;
                                                $FechaDocEtrada = $Movimientos->Fecha;
                                            } else
                                                $FechaDocEtrada = date('Y-m-d H:i:s');

                                            //Notificaciones::GenerarNotificacionesMov(1, $IdMovimiento);

                                            $sql = "UPDATE movimientos_det SET FechaDet='" . $FechaDocEtrada . "' WHERE IdMovimiento=" . $IdMovimiento;
                                            $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                                            $command->execute();
                                            //crear validacion del concepto

                                            if (funciones::AplicaAlistamientoAut($IdMovimiento)) {
                                                funciones::GenerarAlistamiento($IdMovimiento);
                                            }
                                            $Afectada = true;
                                        } else {
                                            if ($Vali == true && $ValiReg == false) {
                                                funciones::ActualizarEstadoDetAll($IdMovimiento, "DIGITADO", -1, $Movimientos->IdDocumento, $objVista);
                                            } else if ($Vali == false && $ValiReg == true) {
                                                funciones::RegistrarAfectada($IdMovimiento, 2, $objVista, $RegistrarAfectada);
                                            }
                                            return false;
                                        }
                                    }
                                } else {
                                    funciones::Mensaje("No se puede autorizar el documento por que tiene cantidades en cero.", 1, $objVista);
                                }
                            } else {
                                funciones::Mensaje("No se puede autorizar un documento con valores en negativo.", 2, $objVista);
                            }
                        }
                    }
                }

                //Cerrar
                if ($Tipo == 4) {
                    if (funciones::ComprobarPermiso($Movimientos->IdDocumento, 8, $objVista) == true) {
                        if ($Movimientos->Estado == "AUTORIZADA" || ($Movimientos->IdDocumento == 85 && $Movimientos->IdConcepto == 179)) {
                            $sql = "UPDATE movimientos_det SET Estado='CERRADO' WHERE IdMovimiento=" . $IdMovimiento;
                            $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                            $command->execute();

                            if ($Movimientos->TpDocumento == 3) {
                                $MovDet = MovimientosDetRecord::finder()->findAllBy_IdMovimiento($IdMovimiento);
                                for ($i = 0; $i < count($MovDet); $i++) {
                                    $item = ItemRecord::finder()->FindByPk($MovDet[$i]->Id_Item);
                                    $item->CantOC = $item->CantOC - ($MovDet[$i]->Cantidad - $MovDet[$i]->CantAfectada);
                                    $item->save();
                                }
                            }
                            $Afectada = true;
                            $Movimientos->Estado = "CERRADA";
                        }
                    }
                }

                //Abrir
                if ($Tipo == 5) {
                    if (funciones::ComprobarPermiso($Movimientos->IdDocumento, 7, $objVista) == true) {
                        if ($Movimientos->Estado == "CERRADA") {

                            $arrayMovDet = MovimientosDetRecord::finder()->findAllBy_IdMovimiento($IdMovimiento);
                            foreach ($arrayMovDet as $Dato) {
                                $Pendiente = $Dato->Cantidad - $Dato->CantAfectada;
                                if ($Pendiente > 0) {
                                    $sql = "UPDATE movimientos_det SET Estado='AUTORIZADO' WHERE IdMovimientoDet = " . $Dato->IdMovimientoDet;
                                    $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                                    $command->execute();
                                    if ($Movimientos->TpDocumento == 3) {
                                        $Item = ItemRecord::finder()->FindByPk($Dato->Id_Item);
                                        $Item->CantOC = $Item->CantOC + ($Dato->Cantidad - $Dato->CantAfectada);
                                        $Item->save();
                                    }
                                }
                            }

                            $Afectada = true;
                            $Movimientos->Estado = "AUTORIZADA";
                        }
                    }
                }

                //Notificar accion
                if ($Comentarios != '') {
                    funciones::NotificarAccion($Movimientos->IdDocumento, $IdAccion, $IdMovimiento, $objVista->User->Name, '', $Comentarios);
                } else {
                    funciones::NotificarAccion($Movimientos->IdDocumento, $IdAccion, $IdMovimiento, $objVista->User->Name);
                }


                if ($Afectada == true) {
                    $Movimientos->save();
                    funciones::CrearLog($Tipo, $IdMovimiento, $objVista->User->Name);
                    return true;
                } else {
                    return false;
                }
            } else {
                funciones::Mensaje("No se puede actualizar el estado del documento con el mismo estado actual de este, Verifique por favor.", 2, $objVista);
            }
        } else {
            funciones::Mensaje("No puedes cambiar el estado del documento ya que se esta generando requerimiento de compras, intenta de nuevo en 3 minutos.", 2, $objVista);
            return false;
        }
    }

    /**
     * IdMovimiento, Obligatorio
     * Tipo, DIGITADA,AUTORIZADA,CERRADA,ANULADA
     * objVista, OBJETO DE LA VISTA ACTUAL
     * IdAccion = 0, Igual a "Tipo"
     * Comentarios = '', 
     * RegistrarAfectada = true
     */
    public static function ActualizarEstado($IdMovimiento, $Tipo, $objVista, $IdAccion = 0, $Comentarios = '', $RegistrarAfectada = true) {
        try {
            $EstadoAplicado = false;
            $Movimiento = MovimientosRecord::finder()->with_Concepto()->with_DocumentoMov()->findByPk($IdMovimiento);
            $requerimientoGenerando = funciones::ValidarGeneracionRequerimiento($IdMovimiento, false);
            $strEstadoTipo = '';
            if (!$requerimientoGenerando) {
                switch ($Tipo) {
                    case 1:
                        $strEstadoTipo = 'ANULADA';
                        $EstadoAplicado = funciones::EstadoAnulada($Movimiento, $objVista, $Comentarios, $RegistrarAfectada);
                        break;

                    case 2:
                        $strEstadoTipo = 'AUTORIZADA';
                        $EstadoAplicado = funciones::EstadoAutorizado($Movimiento, $objVista, $Comentarios, $RegistrarAfectada);
                        break;

                    case 3:
                        $strEstadoTipo = 'DIGITADA';
                        $EstadoAplicado = funciones::EstadoDigitada($Movimiento, $objVista, $Comentarios, $RegistrarAfectada);
                        break;

                    case 4:
                        $strEstadoTipo = 'CERRADA';
                        $EstadoAplicado = funciones::EstadoCerrada($Movimiento, $objVista, $Comentarios);
                        break;

                    case 5:
                        $strEstadoTipo = 'ABRIR';
                        $EstadoAplicado = funciones::EstadoAbrir($Movimiento, $objVista, $Comentarios, $RegistrarAfectada);
                        break;
                }
                //Notificar accion
                if ($Comentarios != '') {
                    funciones::NotificarAccion($Movimiento->IdDocumento, $IdAccion, $IdMovimiento, $objVista->User->Name, '', $Comentarios);
                } else {
                    funciones::NotificarAccion($Movimiento->IdDocumento, $IdAccion, $IdMovimiento, $objVista->User->Name);
                }


                if ($EstadoAplicado == true) {
                    funciones::CrearLog($Tipo, $IdMovimiento, $objVista->User->Name);
                    funciones::Mensaje("Se actualizo el estado a " . $strEstadoTipo, 2, $objVista);
                    return true;
                } else {
                    //funciones::Mensaje("No se pudo actualizar el estado a " . $strEstadoTipo.", valida bien los datos e intenta nuevamente", 2, $objVista);
                    return false;
                }
            } else {
                funciones::Mensaje("No puedes cambiar el estado del documento ya que se esta generando requerimiento de compras, intenta de nuevo en 3 minutos.", 2, $objVista);
                return false;
            }
        } catch (Exception $e) {
            funciones::Mensaje("Ocurrio un error al actualizar el estado " . $e->getMessage(), 2, $objVista);
            return false;
        }
    }

    public static function EstadoDigitada($Movimiento, $objVista, $Comentarios = '', $RegistrarAfectada) {
        $RegitraAfectada = false;
        try {
            if ($Movimiento->Estado != 'DIGITADA') {
                $IdMovimiento = $Movimiento->IdMovimiento;
                $ValidPermiso = funciones::ValidarPermisosActualizarEstadosMov($Movimiento, 3, $objVista);
                if ($ValidPermiso) {
                    if ($Movimiento->Impresion < 1 || funciones::IsAdmin($objVista->User->Name) == true || $Movimiento->IdDocumento == 8 || (funciones::PermisosConsultas($objVista->User->Name, 40) == true && $Movimiento->IdDocumento != 1) || funciones::ValidarSegundaAutorizacion(20, $IdMovimiento, '', $objVista) == true || ($Movimiento->TpDocumento == 33)) {
                        $RegitraAfectada = funciones::RegistrarAfectada($IdMovimiento, 2, $objVista, $RegistrarAfectada);
                        if ($RegistrarAfectada) {
                            $Movimiento->Estado = "DIGITADA";
                            $Movimiento->Autorizado = 0;
                            $Movimiento->IdAutoriza = "";
                            $ActualizarEstadoDet = funciones::ActualizarEstadoDetAll($IdMovimiento, "DIGITADO", -1, $Movimiento->IdDocumento, $objVista);
                            if (!$ActualizarEstadoDet)
                                throw new Exception("Ocurrio un error al actualizar el estado de los detalles");
                            $Movimiento->save();
                            return true;
                        }
                        else {
                            return false;
                        }
                    } else {
                        funciones::Mensaje("No se pueden desautorizar documentos impresos", 2, $objVista);
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                funciones::Mensaje("No puedes desautorizar el documento ya que este se encuentra en DIGITADA", 2, $objVista);
                return false;
            }
        } catch (Exception $e) {
            //Si registra la afectada y ocurre un error lo volvemos a devolver
            if ($RegitraAfectada) {
                funciones::RegistrarAfectada($IdMovimiento, 1, $objVista, $RegistrarAfectada, false, false);
            }
            funciones::Mensaje("Ocurrio un error al actualizar el estado " . $e->getMessage(), 2, $objVista);
            return false;
        }
    }

    public static function EstadoAutorizado($Movimiento, $objVista, $Comentarios = '', $RegistrarAfectada) {
        $ActualizoEstado = false;
        $RegistroAfectada = false;
        try {
            if ($Movimiento->Estado != 'AUTORIZADA') {
                $IdMovimiento = $Movimiento->IdMovimiento;
                $ValidPermiso = funciones::ValidarPermisosActualizarEstadosMov($Movimiento, 2, $objVista);
                if ($ValidPermiso) {
                    $ValidCantidadesCorrectas = funciones::ValidarCantidadesEnlace($IdMovimiento, $objVista);
                    if ($ValidCantidadesCorrectas) {
                        if ($Movimiento->Total >= 0) {
                            // Comprueba que no tenga cantidades en cero(0).
                            $CompobarExisteRegistros = funciones::ComprobarCantRegistros($Movimiento->IdMovimiento, 1);
                            $ComprobarCantiades = funciones::ComprobarCantRegistros($Movimiento->IdMovimiento, 2);
                            if ($CompobarExisteRegistros && $ComprobarCantiades) {
                                if ($Movimiento->Estado == "DIGITADA") {
                                    funciones::GuardarCantAjustada($Movimiento->IdMovimiento);
                                    $RegistroAfectada = funciones::RegistrarAfectadaPerf($IdMovimiento, 1, $objVista, true);
                                    $ActualizoEstado = funciones::ActualizarEstadoDetAll($IdMovimiento, "AUTORIZADO", 1, $Movimiento->IdDocumento, $objVista);
                                    if ($RegistroAfectada == true && $ActualizoEstado == true) {
                                        $Movimiento->Estado = "AUTORIZADA";
                                        $Movimiento->Autorizado = 1;
                                        $Movimiento->IdAutoriza = $objVista->User->Name;
                                        $Movimiento->FhAutoriza = date('y-m-d H:i:s');
                                        //Para que las facturas sin imprimir queden con la fecha de autorizacion
                                        if ($Movimiento->IdDocumento == 3)
                                            $Movimiento->Fecha = $Movimiento->FhAutoriza;

                                        if ($Movimiento->DocumentoMov->Operacion == 1) {
                                            $Movimiento->FhAutoriza = $Movimiento->Fecha;
                                            $FechaDocEtrada = $Movimiento->Fecha;
                                        } else {
                                            $FechaDocEtrada = date('Y-m-d H:i:s');
                                        }
                                        $Movimiento->save();

                                        //Notificaciones::GenerarNotificacionesMov(1, $IdMovimiento);

                                        $sql = "UPDATE movimientos_det SET FechaDet='" . $FechaDocEtrada . "' WHERE IdMovimiento=" . $IdMovimiento;
                                        $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                                        $command->execute();
                                        //crear validacion del concepto

                                        if (funciones::AplicaAlistamientoAut($IdMovimiento)) {
                                            funciones::GenerarAlistamiento($IdMovimiento);
                                        }
                                        return true;
                                    } else {
                                        //Devolvemos a los estados o las afectadas anteriores si ocurre un error
                                        if ($ActualizoEstado == true && $RegistroAfectada == false) {
                                            funciones::ActualizarEstadoDetAll($IdMovimiento, "DIGITADO", -1, $Movimiento->IdDocumento, $objVista);
                                        } else if ($ActualizoEstado == false && $RegistroAfectada == true) {
                                            funciones::RegistrarAfectada($IdMovimiento, 2, $objVista, $RegistrarAfectada);
                                        } else if ($ActualizoEstado && $RegistrarAfectada) {
                                            funciones::ActualizarEstadoDetAll($IdMovimiento, "DIGITADO", -1, $Movimiento->IdDocumento, $objVista);
                                            funciones::RegistrarAfectada($IdMovimiento, 2, $objVista, $RegistrarAfectada);
                                        }
                                        return false;
                                    }
                                } else {
                                    funciones::Mensaje("El documento debe estar DIGITADA", 1, $objVista);
                                    return false;
                                }
                            } else {
                                funciones::Mensaje("No se puede autorizar el documento por que tiene cantidades en cero.", 1, $objVista);
                                return false;
                            }
                        } else {
                            funciones::Mensaje("No se puede autorizar un documento con valores en negativo o en 0.", 2, $objVista);
                            return false;
                        }
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                funciones::Mensaje("No puedes autorizar el documento ya que este se encuentra AUTORIZADA", 2, $objVista);
                return false;
            }
        } catch (Exception $e) {
            funciones::Mensaje("Ocurrio un error al actualizar el estado " . $e->getMessage(), 2, $objVista);
            return false;
        }
    }

    public static function EstadoCerrada($Movimiento, $objVista, $Comentarios = '') {
        try {
            if ($Movimiento->Estado != 'CERRADA') {
                $ValidPermiso = funciones::ValidarPermisosActualizarEstadosMov($Movimiento, 4, $objVista);
                if ($ValidPermiso) {
                    if ($Movimiento->Estado == "AUTORIZADA" || ($Movimiento->IdDocumento == 85 && $Movimiento->IdConcepto == 179)) {
                        $sql = "UPDATE movimientos_det SET Estado='CERRADO' WHERE IdMovimiento=" . $Movimiento->IdMovimiento;
                        $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                        $command->execute();

                        if ($Movimiento->TpDocumento == 3) {
                            $MovDet = MovimientosDetRecord::finder()->findAllBy_IdMovimiento($Movimiento->IdMovimiento);
                            for ($i = 0; $i < count($MovDet); $i++) {
                                $item = ItemRecord::finder()->FindByPk($MovDet[$i]->Id_Item);
                                $item->CantOC = $item->CantOC - ($MovDet[$i]->Cantidad - $MovDet[$i]->CantAfectada);
                                $item->save();
                            }
                        }
                        $Movimiento->Estado = "CERRADA";
                        $Movimiento->save();
                        return true;
                    } else {
                        funciones::Mensaje("No puedes cerrar el documento por que no se encuntra AUTORIZADA", 2, $objVista);
                        return false;
                    }
                    return true;
                } else {
                    return false;
                }
            } else {
                funciones::Mensaje("No puedes actualizar un documento por el mismo estado", 2, $objVista);
                return false;
            }
        } catch (Exception $e) {
            funciones::Mensaje("Ocurrio un error al actualizar el estado " . $e->getMessage(), 2, $objVista);
            return false;
        }
    }

    public static function EstadoAnulada($Movimiento, $objVista, $Comentarios = '') {
        try {
            $Permiso = funciones::ValidarPermisosActualizarEstadosMov($Movimiento, 5, $objVista);
            if ($Permiso) {
                $IdMovimiento = $Movimiento->IdMovimiento;
                if (MovimientosRecord::Impresiones($IdMovimiento) == 0 && ($Movimiento->TpDocumento == 5 || $Movimiento->TpDocumento == 7)) {
                    funciones::Mensaje("No se puede anular un documento que no ha sido impreso.", 2, $objVista);
                    return false;
                }
                $Movimiento->Estado = "ANULADA";
                $Movimiento->Anulado = 1;
                $DevolverCantidades = funciones::DevolverCantidades($IdMovimiento, $Movimiento->DocumentoMov->AfectaCantRef, $Movimiento->TpDocumento);
                $ActualizarEstadoDetalles = funciones::ActualizarEstadoDetAll($IdMovimiento, "ANULADO", -1, $Movimiento->IdDocumento, $objVista);

                $Movimiento->VrIva = 0;
                $Movimiento->VrDcto = 0;
                $Movimiento->VrOtros = 0;
                $Movimiento->VrRetencion = 0;
                $Movimiento->SubTotal = 0;
                $Movimiento->VrFletes = 0;
                $Movimiento->VrRteIva = 0;
                $Movimiento->Total = 0;
                $Movimiento->Costo = 0;
                $Movimiento->VrRteFuente = 0;
                $Movimiento->VrRteCree = 0;

                //Colocar las retenciones y otros valores en cero
                $sql = "UPDATE movimientos_otros SET Debito=0, Credito=0 Where IdMovimiento=" . $IdMovimiento;
                $command = Movimientos_OtrosRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();

                $sql = "UPDATE movimientos_retenciones SET BaseRetencion=0, VrRetencion=0 Where IdMovimiento=" . $IdMovimiento;
                $command = Movimientos_RetencionesRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();

                //Volver a abrir los documentos enlace
                $MovDetLib = MovimientosDetRecord::finder()->FindAllBy_IdMovimiento($IdMovimiento);
                for ($i = 0; $i < count($MovDetLib); $i++) {
                    if ($MovDetLib[$i]->Enlace != NULL)
                        MovimientosDetRecord::AbrirAutoDet($MovDetLib[$i]->Enlace);
                }

                for ($i = 0; $i < count($MovDetLib); $i++) {
                    if ($MovDetLib[$i]->IdDocumento == 1) {
                        $Item = ItemRecord::finder()->FindByPk($MovDetLib[$i]->Id_Item);
                        $Item->CantOC = $Item->CantOC + $MovDetLib[$i]->Cantidad;
                        $Item->save();
                    }
                }

                $sql = "UPDATE movimientos_det SET Cantidad=0, Total=0, Precio=0, Costo=0, CostoPromedio=0, SubTotal=0, PorIva=0, CantDescuento=0, CantDescuento=0, Enlace=NULL WHERE IdMovimiento=" . $IdMovimiento;
                $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();
                $Movimiento->save();
                funciones::Liquidar($IdMovimiento);
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            funciones::Mensaje("Ocurrio un error al actualizar el estado " . $e->getMessage(), 2, $objVista);
            return false;
        }
    }

    public static function EstadoAbrir($Movimiento, $objVista, $Comentarios = '') {
        try {
            $Permiso = funciones::ValidarPermisosActualizarEstadosMov($Movimiento, 6, $objVista);
            if ($Permiso) {
                if ($Movimiento->Estado == "CERRADA") {
                    $arrayMovDet = MovimientosDetRecord::finder()->findAllBy_IdMovimiento($Movimiento->IdMovimiento);
                    foreach ($arrayMovDet as $Dato) {
                        $Pendiente = $Dato->Cantidad - $Dato->CantAfectada;
                        if ($Pendiente > 0) {
                            $sql = "UPDATE movimientos_det SET Estado='AUTORIZADO' WHERE IdMovimientoDet = " . $Dato->IdMovimientoDet;
                            $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                            $command->execute();
                            if ($Movimiento->TpDocumento == 3) {
                                $Item = ItemRecord::finder()->FindByPk($Dato->Id_Item);
                                $Item->CantOC = $Item->CantOC + ($Dato->Cantidad - $Dato->CantAfectada);
                                $Item->save();
                            }
                        }
                    }
                    $Movimiento->Estado = "AUTORIZADA";
                    $Movimiento->save();
                    return true;
                } else {
                    funciones::Mensaje("No puedes actualizar el estado por que no se encuentra CERRADA", 2, $objVista);
                    return false;
                }
            } else {
                return false;
            }
        } catch (Exception $e) {
            funciones::Mensaje("Ocurrio un error al actualizar el estado " . $e->getMessage(), 2, $objVista);
            return false;
        }
    }

    /**
     * Movimiento OBJETO CON LOS DATOS DEL MOVIMIENTO QUE SE ESTA ACTUALIZANDO
     * Accion TIPO DE ACTUALIZACION
     * objVista Objeto de vista desde donde se est ejecutando la accion
     */
    public static function ValidarPermisosActualizarEstadosMov($Movimiento, $Accion, $objVista) {
        $Valid = false;
        $Opcion = null;
        switch ($Accion) {
            case 1:
                $Opcion = 4;
                break;

            case 2:
                $Opcion = 5;
                break;

            case 3:
                $Opcion = 6;
                break;

            case 4:
                $Opcion = 8;
                break;

            case 5:
                $Opcion = 4;
                break;

            case 6:
                $Opcion = 7;
                break;
        }
        if (funciones::ComprobarPermiso($Movimiento->IdDocumento, $Opcion, $objVista) == true || (($Movimiento->IdDocumento == 5 || $Movimiento->IdDocumento == 15 || $Movimiento->IdDocumento == 68 || $Movimiento->IdDocumento == 31 || $Movimiento->IdDocumento == 36 || $Movimiento->IdDocumento == 84) && (funciones::ValidarSegundaAutorizacion(38, $Movimiento->IdMovimiento, '', $objVista) == true) || (funciones::ValidarSegundaAutorizacion(38, $Movimiento->IdMovimiento, '', $objVista) == true) || funciones::ValidarSegundaAutorizacion(16, $Movimiento->IdMovimiento, '', $objVista) == true)) {
            $Valid = true;
        }
        return $Valid;
    }

    /**
     * MovDetDatos objeto del movimiento det 
     * RegistrarAfectada
     * Opcion 1 si afecta 2 si retorna
     */
    public static function ExcentosRegistrarAfectada($MovDetDatos, $RegistrarAfectada, $Opcion) {
        $Valid = false;
        if ($Opcion) {
            if ((($MovDetDatos->IdDocumento == 34 || $MovDetDatos->IdDocumento == 72) && $MovDetDatos->Operacion <> 0) || $Movimiento->IdConcepto == 179 || ($MovDetDatos->MovDet && $MovDetDatos->MovDet->IdDocumento == 75) || $RegistrarAfectada == false) {
                $Valid = true;
            }
        } else if ($Opcion) {
            if ((($MovDetDatos->IdDocumento == 34 || $MovDetDatos->IdDocumento == 72) && $MovDetDatos->Operacion <> 0) || $MovDetDatos->IdDocumento == 36 || $MovDetDatos->TpDocumento == 33) {
                return true;
            }
        }
        return $Valid;
    }

    /**
     * 
     * @param type $IdMovimiento
     * @param type $Estado
     * @param type $Accion
     * @param type $IdDocumento
     * @param type $objVista
     * @param type $IsMovDet
     * @param type $Op
     * @return type
     */
    private static function ActualizarEstadoDetAll($IdMovimiento, $Estado, $Accion, $IdDocumento, $objVista, $IsMovDet = false, $Op = true) {

        if (funciones::ValidarGeneracionRequerimiento($IdMovimiento, $IsMovDet) == false) {
            $TipoMov = "IdMovimiento";
            if ($IsMovDet == true) {
                $TipoMov = "IdMovimientoDet";
            }
            $Mov = new MovimientosRecord();
            $Mov = MovimientosRecord::finder()->with_Concepto()->findByPk($IdMovimiento);
            if ($TipoMov == 'IdMovimientoDet') {
                $MovDetVal = MovimientosDetRecord::finder()->FindByPK($IdMovimiento);
                $Mov = new MovimientosRecord();
                $Mov = MovimientosRecord::finder()->with_Concepto()->findByPk($MovDetVal->IdMovimiento);
            }
            $Documentos = DocumentosRecord::finder()->findByPk($IdDocumento);
            $TpDocumento = TpDocumentosRecord::finder()->findByPk($Documentos->Tp);
            $AfectaInventario = $Documentos->Operacion * $Accion;
            $NroReg = 0;
            if ($Documentos->AfectaTraslado == 1)
                $AfectaInventario = -1;


            $SinErrores = true;
            if ($AfectaInventario == 1 || $AfectaInventario == -1 || $Documentos->AfectaReserva == 1) {
                $sql = "SELECT movimientos_det .* FROM movimientos_det WHERE $TipoMov =" . $IdMovimiento;
                $MovimientoDet = MovimientosDetRecord::finder()->with_Item()->findAllBySql($sql);
                $NroReg = count($MovimientoDet);
                //Comprobar existencias
                $i = 0;
                while ($i < $NroReg && $SinErrores == true) {
                    if ($MovimientoDet[$i]->Item->AfectaInventario == 1 && $Documentos->Operacion != 0)
                        $SinErrores = funsop1::CpEx($MovimientoDet[$i]->IdMovimiento, $MovimientoDet[$i]->IdMovimientoDet, $MovimientoDet[$i]->Id_Item, $MovimientoDet[$i]->Lote, $MovimientoDet[$i]->Bodega, $MovimientoDet[$i]->Operacion * $Accion, $MovimientoDet[$i]->Cantidad, $TpDocumento->Comodato, $objVista);
                    $i++;
                }
                $i = 0; //Afectar reservas
                while ($i < $NroReg && $SinErrores == true && ($Accion == 1 || $Accion == -1)) {
                    if ($Op == true && $MovimientoDet[$i]->Estado != "AUTORIZADO" || $Op == false) {
                        $Mov = new MovimientosRecord();
                        $Mov = MovimientosRecord::finder()->with_Concepto()->FindByPk($MovimientoDet[$i]->IdMovimiento);
                        if ($MovimientoDet[$i]->Item->AfectaInventario == 1) {
                            if (($MovimientoDet[$i]->TpDocumento == 5 || $MovimientoDet[$i]->TpDocumento == 7) && ($MovimientoDet[$i]->IdDocumento != 36 && $MovimientoDet[$i]->IdDocumento != 84)) {
                                $rsItem = ItemRecord::finder()->findByPk($MovimientoDet[$i]->Id_Item);
                                if (!funciones::ValidarDisponiblesLote($MovimientoDet[$i]->IdMovimientoDet, true, $objVista)) {
                                    return false;
                                }

                                if ($rsItem->AfectaInventario == 1) { //Si el item maneja inventario
                                    $sql = "Select reservas.* from reservas where IdMovimientoDetRes=" . $MovimientoDet[$i]->Enlace . " and Id_ItemRes=" . $MovimientoDet[$i]->Id_Item . " and LoteRes='" . $MovimientoDet[$i]->Lote . "' and BodegaRes=" . $MovimientoDet[$i]->Bodega;
                                    $reservas = ReservasRecord::finder()->findBySql($sql);
                                    if (count($reservas) > 0) {
                                        if ($reservas->CantidadRes <= $MovimientoDet[$i]->Cantidad) {

                                            $lotes = LotesRecord::finder()->findByPk($reservas->Id_ItemRes, $reservas->LoteRes, $reservas->BodegaRes);
                                            if (count($lotes) > 0) {
                                                if ($Accion == 1) {
                                                    $lotes->Reserva = $lotes->Reserva - $reservas->CantidadRes;
                                                    $lotes->save();

                                                    $MovDet = MovimientosDetRecord::finder()->findByPk($MovimientoDet[$i]->Enlace);
                                                    $MovDet->Cantidad2 = $MovDet->Cantidad2 - $reservas->CantidadRes;
                                                    $MovDet->save();

                                                    $MovDet1 = MovimientosDetRecord::finder()->findByPk($MovimientoDet[$i]->IdMovimientoDet);
                                                    $MovDet1->Cantidad2 = $reservas->CantidadRes;
                                                    $MovDet1->save();

                                                    $reservas->CantidadRes = 0;
                                                    $reservas->save();
                                                }
                                                if ($Accion == -1) {
                                                    $lotes->Reserva = $lotes->Reserva + $MovimientoDet[$i]->Cantidad2;
                                                    $lotes->save();
                                                    $reservas->CantidadRes = $reservas->CantidadRes + $MovimientoDet[$i]->Cantidad2;
                                                    $reservas->save();
                                                    $MovDet = MovimientosDetRecord::finder()->findByPk($MovimientoDet[$i]->Enlace);
                                                    $MovDet->Cantidad2 = $MovDet->Cantidad2 + $MovimientoDet[$i]->Cantidad2;
                                                    $MovDet->save();

                                                    $MovDet1 = MovimientosDetRecord::finder()->findByPk($MovimientoDet[$i]->IdMovimientoDet);
                                                    $MovDet1->Cantidad2 = 0;
                                                    $MovDet1->save();
                                                }
                                            }
                                        } else {
                                            $lotes = LotesRecord::finder()->findByPk($reservas->Id_ItemRes, $reservas->LoteRes, $reservas->BodegaRes);
                                            if (count($lotes) > 0) {
                                                if ($Accion == 1) {
                                                    $lotes->Reserva = $lotes->Reserva - $MovimientoDet[$i]->Cantidad;
                                                    $lotes->save();
                                                    $reservas->CantidadRes = $reservas->CantidadRes - $MovimientoDet[$i]->Cantidad;
                                                    $reservas->save();
                                                    $MovDet = MovimientosDetRecord::finder()->findByPk($MovimientoDet[$i]->Enlace);
                                                    $MovDet->Cantidad2 = $MovDet->Cantidad2 - $MovimientoDet[$i]->Cantidad;
                                                    $MovDet->save();

                                                    $MovDet1 = MovimientosDetRecord::finder()->findByPk($MovimientoDet[$i]->IdMovimientoDet);
                                                    $MovDet1->Cantidad2 = $MovimientoDet[$i]->Cantidad;
                                                    $MovDet1->save();
                                                }

                                                if ($Accion == -1) {
                                                    $lotes->Reserva = $lotes->Reserva + $MovimientoDet[$i]->Cantidad2;
                                                    $lotes->save();
                                                    $reservas->CantidadRes = $reservas->CantidadRes + $MovimientoDet[$i]->Cantidad2;
                                                    $reservas->save();
                                                    $MovDet = MovimientosDetRecord::finder()->findByPk($MovimientoDet[$i]->Enlace);
                                                    $MovDet->Cantidad2 = $MovDet->Cantidad2 + $MovimientoDet[$i]->Cantidad2;
                                                    $MovDet->save();

                                                    $MovDet1 = MovimientosDetRecord::finder()->findByPk($MovimientoDet[$i]->IdMovimientoDet);
                                                    $MovDet1->Cantidad2 = 0;
                                                    $MovDet1->save();
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $i++;
                }

                //$SinErrores=false;
                $i = 0;
                while ($i < $NroReg && $SinErrores == true) {
                    if ($MovimientoDet[$i]->Operacion != 0 && $MovimientoDet[$i]->Item->AfectaInventario == 1) {
                        //Se crea validacion para que no vuelva a afectar la cantidad si el detalle ya esta autorizado o si la accion es desautorizar para que lo retorne.
                        if ($Mov->IdConcepto == null || $Mov->Concepto->Opcion == 0) {
                            if ($Op == true && $MovimientoDet[$i]->Estado != "AUTORIZADO" || $Op == false || $Accion == -1) {
                                funciones::AfectarInventario($MovimientoDet[$i]->IdMovimientoDet, $Accion, $TpDocumento->Comodato);
                            }
                        }
                    }
                    $i++;
                }
            }

            if ($SinErrores == true) {
                if ($Estado == "AUTORIZADO") {
                    $sql = "UPDATE movimientos_det SET Estado='" . $Estado . "' WHERE $TipoMov=" . $IdMovimiento . " and Estado='DIGITADO'";
                    $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();
                } elseif ($Estado == "DIGITADO") {
                    $sql = "UPDATE movimientos_det SET Estado='" . $Estado . "' WHERE $TipoMov=" . $IdMovimiento . " and Estado='AUTORIZADO'";
                    $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();
                } else {
                    $sql = "UPDATE movimientos_det SET Estado='" . $Estado . "' WHERE $TipoMov=" . $IdMovimiento;
                    $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();
                }

                for ($i = 0; $i < $NroReg; $i++) {
                    funciones::ActualizarPedCom($MovimientoDet[$i]->Id_Item, $MovimientoDet[$i]->IdDocumento, $MovimientoDet[$i]->IdMovimiento, $MovimientoDet[$i]->IdMovimientoDet, $Accion);
                }
            }
            return $SinErrores;
        }
        funciones::Mensaje("No puedes cambiar el estado del documento ya que se esta generando requerimiento, intenta de nuevo en 3 minutos.", 2, $objVista);
        return false;
    }

    public static function AfectarInventario($IdMovimientoDet, $Accion, $Comodato) {
        $MovDet = MovimientosDetRecord::finder()->findByPk($IdMovimientoDet);
        $Item = $MovDet->Id_Item;
        $Lote = $MovDet->Lote;
        $Bodega = $MovDet->Bodega;
        $FhVencimiento = $MovDet->FhVencimiento;
        $Cantidad = $MovDet->Cantidad * $MovDet->Operacion;
        $Costo = $MovDet->Costo;

        //Por si el documento es comodato
        if ($Comodato == 0)
            $Lotes = LotesRecord::finder()->findByPk($Item, $Lote, $Bodega);
        else
            $Lotes = LotesComodatosRecord::finder()->findByPk($Item, $Lote, $Bodega);

        if (count($Lotes) <= 0) {
            funciones::CrearLote($Item, $Lote, $FhVencimiento, $Bodega, $Comodato);
        }

        //Para las remisiones
        if ($MovDet->TpDocumento == 7) {
            if ($Accion == 1) {
                $sql = "UPDATE item SET Remisionada=Remisionada-" . $Cantidad * $Accion . ", Disponible=Existencia-(Remisionada+Reserva) WHERE Id_Item=" . $Item;
                $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();

                $sql = "UPDATE lotes SET Remisionada=Remisionada-" . $Cantidad * $Accion . ", Disponible=Existencia-(Remisionada+Reserva) WHERE Id_Item=" . $Item . " and Lote='" . $Lote . "' and Bodega=" . $Bodega;
                $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();
            } elseif ($Accion == -1) {
                $sql = "UPDATE item SET Remisionada=Remisionada-" . $Cantidad * $Accion . ", Disponible=Disponible+" . $Cantidad * $Accion . " WHERE Id_Item=" . $Item;
                $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();

                $sql = "UPDATE lotes SET Remisionada=Remisionada-" . $Cantidad * $Accion . ", Disponible=Disponible+" . $Cantidad * $Accion . " WHERE Id_Item=" . $Item . " and Lote='" . $Lote . "' and Bodega=" . $Bodega;
                $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();
            }
        }

        // Para las facturas.
        elseif ($MovDet->TpDocumento == 5) {
            $MovDetEnlace = new MovimientosDetRecord;
            $MovDetEnlace = MovimientosDetRecord::finder()->findByPk($MovDet->Enlace);

            if ($MovDetEnlace->TpDocumento == 7) { // Si el item fue remisionado.
                if ($Accion == 1) {
                    $sql = "UPDATE item SET Remisionada=Remisionada-" . $MovDet->Cantidad . " WHERE Id_Item=" . $Item;
                    $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();

                    $sql = "UPDATE lotes SET Remisionada=Remisionada-" . $MovDet->Cantidad . " WHERE Id_Item=" . $Item . " and Lote='" . $Lote . "' and Bodega=" . $Bodega;
                    $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();
                } else {
                    $sql = "UPDATE item SET Remisionada=Remisionada+" . $MovDet->Cantidad . " WHERE Id_Item=" . $Item;
                    $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();

                    $sql = "UPDATE lotes SET Remisionada=Remisionada+" . $MovDet->Cantidad . " WHERE Id_Item=" . $Item . " and Lote='" . $Lote . "' and Bodega=" . $Bodega;
                    $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();
                }
            }

            $sql = "UPDATE lotes SET Existencia=Existencia+" . $Cantidad * $Accion . ", Disponible=Existencia-(Remisionada+Reserva) WHERE Id_Item=" . $Item . " and Lote='" . $Lote . "' and Bodega=" . $Bodega;
            $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
            $command->execute();

            $sql = "UPDATE item SET Existencia=Existencia+" . $Cantidad * $Accion . ", Disponible=Existencia-(Remisionada+Reserva) WHERE Id_Item=" . $Item;
            $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
            $command->execute();
        } else {
            if ($Comodato == 0) {
                $sql = "UPDATE lotes SET Existencia=Existencia+" . $Cantidad * $Accion . ", Disponible=Disponible+" . $Cantidad * $Accion . " WHERE Id_Item=" . $Item . " and Lote='" . $Lote . "' and Bodega=" . $Bodega;
                $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();

                $sql = "UPDATE item SET Existencia=Existencia+" . $Cantidad * $Accion . ", Disponible=Disponible+" . $Cantidad * $Accion . " WHERE Id_Item=" . $Item;
                $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();
            } else {
                $sql = "UPDATE lotes_comodatos SET Existencia=Existencia+" . $Cantidad * $Accion . ", Disponible=Disponible+" . $Cantidad * $Accion . " WHERE Id_Item=" . $Item . " and Lote='" . $Lote . "' and Bodega=" . $Bodega;
                $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();

                $sql = "UPDATE item SET ExComodato=ExComodato+" . $Cantidad * $Accion . " WHERE Id_Item=" . $Item;
                $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();
            }

            /* $sql = "UPDATE item SET CostoProm=((Existencia*CostoProm)+(".$Cantidad*$Accion."*$Costo))/(Existencia+".$Cantidad*$Accion."), Existencia=Existencia+".$Cantidad*$Accion.", Disponible=Disponible+".$Cantidad*$Accion." WHERE Id_Item=".$Item;
              $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
              $command->execute(); */
        }

        //-------------Costo promedio------------
        if ($Accion == 1) {
            $rstItem = ItemRecord::finder()->findByPk($Item);
            $CostoPromedio = $rstItem->CostoProm;
            if ($MovDet->IdDocumento == 1 || $MovDet->IdDocumento == 30) {
                //Ojo Validacion de existencia cero divizion vi Zero
                /* $CostoPromedio=((($rstItem->Existencia*$rstItem->CostoProm)+($Cantidad*$Costo))/($rstItem->Existencia));
                  $sql = "UPDATE item SET CostoProm=".$CostoPromedio."  WHERE Id_Item=".$Item;
                  $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
                  $command->execute(); */

                $sql = "UPDATE movimientos_det SET CostoPromedio=" . $CostoPromedio . "  WHERE IdMovimientoDet=" . $MovDet->IdMovimientoDet;
                $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();
            }

            if ($MovDet->IdDocumento == 3 || $MovDet->IdDocumento == 31 || $MovDet->IdDocumento == 36 || $MovDet->IdDocumento == 84) {
                $sql = "UPDATE movimientos_det SET Costo=" . $CostoPromedio . ", CostoPromedio=" . $CostoPromedio . "  WHERE IdMovimientoDet=" . $MovDet->IdMovimientoDet;
                $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();
            }
        }
    }

    /**
     * 
     * @param type $IdMovimientoDet
     * @param type $Estado
     * @param type $objVista
     * @param type $IdAccion
     * @return boolean
     */
    public static function ActualizarEstadoDet($IdMovimientoDet, $Estado, $objVista = NULL, $IdAccion = 0) {

        $MovimientosDet = MovimientosDetRecord::finder()->findByPk($IdMovimientoDet);
        if (funciones::ValidarGeneracionRequerimiento($IdMovimientoDet, true) == false) {
            if (funciones::ValidarReservas($IdMovimientoDet) <= 0) {
                if ($Estado == "CERRADO") {
                    $sql = "UPDATE movimientos_det SET Estado='" . $Estado . "', Cerrado=1 WHERE IdMovimientoDet=" . $IdMovimientoDet;
                    $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();
                } elseif ($Estado == "AUTORIZADO") {
                    $sql = "UPDATE movimientos_det SET Estado='" . $Estado . "', Cerrado=0 WHERE (Cantidad-CantAfectada)>0 and IdMovimientoDet=" . $IdMovimientoDet;
                    $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();
                    if ($MovimientosDet->IdDocumento == 87) {
                        $Mov = MovimientosRecord::finder()->FindByPk($MovimientosDet->IdMovimiento);
                        if ($Mov->IdConcepto == 179) {
                            $MovDetEnlace = MovimientosDetRecord::finde()->FindByPk($MovimientosDet->Enlace);
                            $MovDetEnlace->Comentarios = $MovimientosDet->Comentarios;
                            $MovDetEnlace->save();
                        }
                    }
                } else {
                    $sql = "UPDATE movimientos_det SET Estado='" . $Estado . "' WHERE IdMovimientoDet=" . $IdMovimientoDet;
                    $command = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();
                }

                funciones::NotificarAccion($MovimientosDet->IdDocumento, $IdAccion, $MovimientosDet->IdMovimiento, $objVista->User->Name);
            } else {
                funciones::Mensaje("No puedes cerrar el item " . $MovimientosDet->Id_Item . " ya que tiene cantidades en reserva, debes eliminarla para continuar y tambien validar si otros productos que estas cerrando tienen el mismo problema.", 2, $objVista);
                return false;
            }
        } else {
            funciones::Mensaje("No puedes cambiar el estado del documento ya que se esta generando requerimiento y esto podria generar inconsistencias en las compras, intenta de nuevo en 3 minutos.", 2, $objVista);
            return false;
        }
    }

    public static function ComprobarEstado($IdMovimiento, $Estado) {
        $Movimientos = new MovimientosRecord;
        $Movimientos = MovimientosRecord::finder()->findByPk($IdMovimiento);

        if ($Movimientos->Estado == $Estado) {
            return 1;
        } else
            return 0;
    }

    public static function SacarConsecutivo($IdDocumento) {
        $DocumentosRecord = new DocumentosRecord;
        $DocumentosRecord = DocumentosRecord::finder()->findByPk($IdDocumento);
        $Consecutivo = $DocumentosRecord->Consecutivo;
        $DocumentosRecord->Consecutivo = $DocumentosRecord->Consecutivo + 1;
        $DocumentosRecord->save();
        return $Consecutivo;
    }

    /**
     * Comprueba si el usuario enviado por parametro a $strUsuario, posee permisos para una determinada operacion
     * segun el tipo de accion que envie como parametro a la variable "Tipo".
     * 1 Crear 
     * 2 Editar
     * 3 Eliminar
     * 4 Anular
     * 5 Autorizar
     * 6 Desautorizar
     * 7 Abrir
     * 8 Cerrar
     * 9 Agregar
     * 10 Quitar
     * 11 Ver Detalle
     * @param integer $IdDocumento El codigo del documento sobre el que el usuario realizo la accion.
     * @param integer $Tipo Tipo de accion que el usuario realizo, crea, editar...
     * @param object $objVista La pagina desde la que se invoca el metodo.
     * 
     * @return boolean true tiene el permiso, false no lo tiene 
     * */
    public static function ComprobarPermiso($IdDocumento, $Tipo, $objVista, $ImpMensaje = true) {
        if (!self::IsAdmin($objVista->User->Name)) {
            $Permiso = false;
            $UsuariosPermisos = new UsuariosPermisosRecord;
            $sql = "SELECT * FROM usuariospermisos WHERE IdUsuario='" . $objVista->User->Name . "' AND IdDocumento=" . $IdDocumento;
            $UsuariosPermisos = UsuariosPermisosRecord::finder()->findBySql($sql);
            if (count($UsuariosPermisos) > 0) {
                switch ($Tipo) {
                    case 1: //Crear-Nuevo
                        if ($UsuariosPermisos->Crear == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 2: //Editar
                        if ($UsuariosPermisos->Editar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 3: //Eliminar
                        if ($UsuariosPermisos->Eliminar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 4: //Anular
                        if ($UsuariosPermisos->Anular == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 5: //Autorizar
                        if ($UsuariosPermisos->Autorizar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 6: //Desautorizar
                        if ($UsuariosPermisos->DesAutorizar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 7: //Abrir
                        if ($UsuariosPermisos->Abrir == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 8: //Cerrar
                        if ($UsuariosPermisos->Cerrar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 9: //Agregar
                        if ($UsuariosPermisos->Agregar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 10: //Quitar
                        if ($UsuariosPermisos->Quitar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 11: //VerDetalle
                        if ($UsuariosPermisos->VerDetalle == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 12: //Ingresar
                        if ($UsuariosPermisos->Ingresar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 13: //Revisar
                        if ($UsuariosPermisos->Revisar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 14: //COnfirmar
                        if ($UsuariosPermisos->Confirmar == 1) {
                            $Permiso = true;
                        }
                        break;

                    case 15: //Autorizar 2
                        if ($UsuariosPermisos->Autorizar2 == 1) {
                            $Permiso = true;
                        }
                        break;
                    case 16://Imprimir 
                        if ($UsuariosPermisos->Imprimir == 1) {
                            $Permiso = true;
                        }
                }

                if ($Permiso == false && ($IdDocumento == 5 || $IdDocumento == 15 || $IdDocumento = 32) && $Tipo == 5) {
                    if ($ImpMensaje) {
                        funciones::Mensaje("Se le ha negado el permiso para esta accion, solicite segunda autorizacion.", 2, $objVista);
                    }
                } else if ($Permiso == false) {
                    if ($ImpMensaje) {
                        funciones::Mensaje("Se le ha negado el permiso para esta accion", 2, $objVista);
                    }
                }
            } else {
                if ($IdDocumento != 90) {
                    if ($ImpMensaje) {
                        funciones::Mensaje("El usuario no tiene asignados permisos para esta accion", 2, $objVista);
                    }
                }
            }

            if ($Permiso == true) {
                return true;
            } else {
                return false;
            }
        } else
            return true;
    }

    public static function DevolverCantidades($IdMovimiento, $AfectaCantRef, $TpDocumento) {
        try {
            if ($AfectaCantRef == 1) {
                if ($TpDocumento == 8) {
                    $sql = "Select IdMovimientoDet, Id_Item, Cantidad, Enlace,IdDocumento from movimientos_det where Operacion=0 and IdMovimiento=" . $IdMovimiento;
                } else {
                    $sql = "Select IdMovimientoDet, Id_Item, Cantidad, Enlace,IdDocumento from movimientos_det where IdMovimiento=" . $IdMovimiento;
                }
                $MovDet = MovimientosDetRecord::finder()->findAllBySql($sql);
                $TReg = count($MovDet);
                for ($i = 0; $i < $TReg; $i++) {
                    if ($MovDet[$i]->Enlace != NULL) {
                        $sql = "UPDATE movimientos_det SET CantAfectada=CantAfectada-" . $MovDet[$i]->Cantidad . " WHERE IdMovimientoDet=" . $MovDet[$i]->Enlace;
                        $rstMovDet = MovimientosDetRecord::finder()->getDbConnection()->createCommand($sql);
                        $rstMovDet->execute();
                    }
                    if ($MovDet[$i]->IdDocumento == 6 || $MovDet[$i]->IdDocumento == 19) {
                        funciones::EliminarDetalleRequerimiento($MovDet[$i]->IdMovimientoDet);
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    Public static function CrearLote($Item, $Lote, $FhVencimiento, $Bodega, $Comodato) {

        //Por si el documento es comodato
        if ($Comodato == 0)
            $LotesNew = new LotesRecord;
        else
            $LotesNew = new LotesComodatosRecord;

        $LotesNew->Id_Item = $Item;
        $LotesNew->Lote = $Lote;
        $LotesNew->FhVencimiento = $FhVencimiento;
        $LotesNew->Bodega = $Bodega;
        $LotesNew->Existencia = 0;
        $LotesNew->Disponible = 0;
        $LotesNew->save();
    }

    /**
     * Crea un registro en la tabla log.
     * @param <type> $IdAccion
     * @param <type> $IdMovimiento
     * @param string $strUsuario
     */
    Public static function CrearLog($IdAccion, $IdMovimiento, $strUsuario) {
        $Mov = new MovimientosRecord();
        $Mov = MovimientosRecord::finder()->FindByPk($IdMovimiento);
        if (isset($Mov)) {
            if (($Mov->Impresion <= 0 && $IdAccion == 7) || ($Mov->Impresion == 1 && $IdAccion == 7 && $Mov->UsuarioImprime == '')) {//Guardamos el usuario que imprime por primera vez el documento
                $Mov->UsuarioImprime = $strUsuario;
                $Mov->FhImprime = date("Y-m-d H:i:s");
                $Mov->save();
            }
        }
        $LogNew = new LogRecord;
        $LogNew->IdAccion = $IdAccion;
        $LogNew->IdMovimiento = $IdMovimiento;
        $LogNew->Usuario = $strUsuario;
        if (isset($Mov)) {
            if ($LogNew->IdAccion == 7 && ($Mov->FhImprime != '' && $Mov->UsuarioImprime == '')) {
                $Mov->UsuarioImprime = $strUsuario;
                $Mov->save();
            }
        }
        $LogNew->Fecha = date('y-m-d H:i:s');
        $LogNew->save();
    }

    /**
     * Crea un registro de Despacho en la tabla log.
     * @param <type> $IdAccion
     * @param <type> $IdDespacho
     * @param string $strUsuario
     */
    Public static function CrearLogDespacho($IdAccion, $IdDespacho, $strUsuario) {
        $LogNew = new LogRecord;
        $LogNew->IdAccion = $IdAccion;
        $LogNew->IdDespacho = $IdDespacho;
        $LogNew->Usuario = $strUsuario;
        $LogNew->Fecha = date('y-m-d H:i:s');
        $LogNew->save();
    }

    Public static function CrearLogReporteNovedades($IdAccion, $IdRepoNov, $strUsuario) {
        $LogNew = new LogRecord;
        $LogNew->IdAccion = $IdAccion;
        $LogNew->IdReporteNovedad = $IdRepoNov;
        $LogNew->Usuario = $strUsuario;
        $LogNew->Fecha = date('y-m-d H:i:s');
        $LogNew->save();
    }

    public static function CrearLogCotizaciones($IdAccion, $IdCotizacion, $strUsuario) {
        $LogNew = new LogCotizacionesRecord;
        $LogNew->IdAccion = $IdAccion;
        $LogNew->IdCotizacion = $IdCotizacion;
        $LogNew->Usuario = $strUsuario;
        $LogNew->Fecha = date('y-m-d H:i:s');
        $LogNew->save();
    }

    Public static function CantDisponible($Item, $Lote, $Bodega) {
        $Lotes = new LotesRecord;
        $Lotes = LotesRecord::finder()->findByPk($Item, $Lote, $Bodega);
        if (count($Lotes) > 0) {
            $CantidadDisponible = $Lotes->Existencia - ($Lotes->Reserva + $Lotes->Remisionada);
        } else {
            $CantidadDisponible = 0;
        }
        return $CantidadDisponible;
    }

    public static function DevTipoTercero($IdDocumento) {
        $Exige = 3;
        $DocumentosRecord = DocumentosRecord::finder()->findByPk($IdDocumento);
        if ($DocumentosRecord->ExigeProveedor == 1) {
            $Exige = 1;
        }
        if ($DocumentosRecord->ExigeCliente == 1) {
            $Exige = 2;
        }
        return $Exige;
    }

    /**
     * Busca el documento de control de un documento especifico
     * 
     * @param integer $intIdDocumento Id del documento
     * @return integer $intDocControl
     * */
    public static function DevTipoDocControl($intIdDocumento) {
        $arDocumentosRecord = DocumentosRecord::finder()->findByPk($intIdDocumento);
        return $arDocumentosRecord->DocControl;
    }

    /**
     * Verifica si el movimiento enviado por parametro tiene novedad,
     * si true devolvera un estilo especifico para ese item del datagrid.
     * */
    public static function ItemNovedad($IdMov, $param) {
        $item = $param->Item;
        $NovedadMov = new MovimientosRecord();
        $NovedadMov = MovimientosRecord::finder()->findByPk($param->Item->DataItem->IdMovimiento);

        if ($NovedadMov->EnNovedad != 0) {
            $param->Item->CssClass = "item-novedad";
        }
    }

    /**
     * Verifica si un producto presenta una novedad de proveedor o interna y muestra un mensaje si es verdadero
     * @param integer $IdItem El id de item que presenta la novedad
     * @param objeto $Vista  La vista actual
     * */
    public static function ProductoNovedad($IdItem, $Vista) {
        $Novedad = NovedadesRecord::finder()->findAll("IdItem = ? AND Solucionada = ?", $IdItem, 0);
        if (Count($Novedad) != 0) {
            $Vista->PnlNovedad->Visible = true;
            $Vista->IBtnNovedades->Attributes->OnClick = "javascript:abrirVentana('?page=busqueda.general.NovedadesItem&IdItem=$IdItem',550, 900);return false;";
        } else {
            $Vista->PnlNovedad->Visible = false;
        }
    }

    /**
     * Verifica si un producto presenta una novedad de proveedor o interna y retorna el conteo de ellas
     * @param integer $IdItem El id de item que presenta la novedad
     * @param objeto $Vista  La vista actual
     * @return integer mixed Numero de novedades que posee el producto.
     * */
    public static function ProductoNovedadDatagrid($IdItem) {
        $Novedad = NovedadesRecord::finder()->findAll("IdItem = ? AND Solucionada = ?", $IdItem, 0);
        return Count($Novedad);
    }

    /**
     * Separa una fecha por Anio, Mes, Dia.
     * @param 
     * @return array $Dato Array con la fecha <p>
     * Dato[o] - Año
     * Dato[1] - Mes
     * Dato[2] - Dia
     * */
    public static function SepararFecha($Fecha) {
        list($year, $month, $day) = explode('-', $Fecha);
        $Datos = array($year, $month, $day);
        return $Datos;
    }

    /**
     * dividimos la fecha inicial en cada una de sus partes y mediante la funcion
     * mktime() obtenemos la marca de tiempo el resultado obtenido es en segundos,
     * restando las fechas 7 dividiendo el resultado por los segundos que tiene
     * un dia obtenemos el total de dias entre las fechas.
     * formato = AAAA/mm/dd
     * @param date $dFecIni
     * @param date $dFecFin
     * */
    public static function restaFechas($dFecIni, $dFecFin) {
        $totalDays = "";
        try {
            $startDate = $dFecIni;
            $endDate = $dFecFin;
            list($year, $month, $day) = explode('/', $startDate);
            $startDate = mktime(0, 0, 0, $month, $day, $year);
            list($year, $month, $day) = explode('/', $endDate);
            $endDate = mktime(0, 0, 0, $month, $day, $year);
            $totalDays = ($endDate - $startDate) / (60 * 60 * 24);
            return $totalDays;
        } catch (Exception $e) {
            return $totalDays;
        }
    }

    /**
     * envia un mail a las direcciiones de correo electronico especificadas en los parametros.
     * @param boolean $Confirmar Direccion de correo al que se puede confirmar la llegada del mail
     * @param string $strDesde La direccion desde donde fue enviada la notificacion
     * @param string $strNmDesde Nombre del Remitente
     * @param string $strAsunto 
     * @param string $strMensaje
     * @param string $strDirecciones, las direcciones de correo electronico donde se pretende enviar la notificacion.
     * @param string $strAdjunto La direccion fisica del archivo que se desea enviar como adjunto
     * @return boolean Falso en caso de error / true y se envia
     * */
    public static function EnviarCorreo($strConfirmar = "", $strDesde, $strNmDesde, $strAsunto, $strMensaje, $strDirecciones, $strAdjunto = "", $strNombreAdjunto = "", $notificacioncotizacion = false) {
        $BoolEmail = false;
        $mail = new PHPMailer();

        $userName = "";
        $password = "";
        if ($notificacioncotizacion) {
            $userName = "notificacioncotizacion@aba.com.co";
            $password = "coti2021*";
        } else {
            $userName = "kasten@aba.com.co";
            $password = "kk1-2021AC";
        }

        /// Configuracion del servidor Smtp
        $mail->IsSMTP();
        $mail->SMTPAuth = true;
        $mail->Priority = 1;
        $mail->Host = "smtp.googlemail.com";
        $mail->Port = 465;
        $mail->Username = $userName;
        $mail->Password = $password;
        $mail->SMTPSecure = "ssl";
        $mail->From = $strDesde;
        $mail->FromName = $strNmDesde;
        $mail->Subject = $strAsunto;
        /// fin configuracion
        // Especificamos que el mail esta en formato html
        $mail->IsHTML(true);

        // Direccion de correo de confirmacion si el dato no es enviado como parametro
        if ($strConfirmar != "")
            $mail->ConfirmReadingTo = $strConfirmar;

        try {
            // Cuerpo del mensaje
            if ($strMensaje != "")
                $mail->MsgHTML($strMensaje);
            else
                $mail->MsgHTML("Enviado desde Kasten <br> ---- ABA CIENTIFICA S.A");

            if (is_array($strDirecciones)) {
                foreach ($strDirecciones as $Direccion) {
                    if (date("D") == "Sat" || date("D") == "Sun") {//el sabado o domingo valida
                        $Dato = strpos($Direccion, 'asesor'); //Si el correo contiene asesor
                        if ($Dato === false) {//No le envia el correo
                            $mail->AddAddress($Direccion);
                            $BoolEmail = true;
                        }
                    } else {
                        $mail->AddAddress($Direccion);
                        $BoolEmail = true;
                    }
                }
            } else {
                if (date("D") == "Sat" || date("D") == "Sun") {
                    $Dato = strpos($Direccion, 'asesor');
                    if ($Dato === false) {
                        $mail->AddAddress($Direccion);
                        $BoolEmail = true;
                    }
                } else {
                    $mail->AddAddress($strDirecciones);
                    $BoolEmail = true;
                }
            }

            // Verificamos si tiene adjunto
            if ($strAdjunto != "")
                if (is_array($strAdjunto)) {
                    foreach ($strAdjunto as $strAdjunto) {
                        $mail->AddAttachment($strAdjunto, "");
                    }
                } else {
                    $mail->AddAttachment($strAdjunto, $strNombreAdjunto);
                }

            if ($BoolEmail == true) {//Valida que hay un correo adjunto
                $mail->Send();
            }
            return true;
        } catch (exception $e) {
            echo $e->getMessage();
//            funciones::Mensaje("No se pudo enviar el mensaje, por favor verifique las direcciones.", 2, $objVista);
            return false;
        }
    }

    public static function ValidarFechas($Fecha1, $Fecha2) {
        $Bool = true;

        if ($Fecha1 == '0000-00-00' || $Fecha1 == NULL || $Fecha2 == '0000-00-00' || $Fecha2 == NULL) {
            $Bool = false;
        }


        if (funciones::restaFechas($Fecha1, $Fecha2) < 0) {
            $Bool = false;
        }

        if ((funciones::restaFechas(date('Y/m/d'), $Fecha1) < 0) || (funciones::restaFechas(date('Y/m/d'), $Fecha2) < 0)) {
            $Bool = false;
        }

        Return $Bool;
    }

    public static function Liquidar($IdMovimiento) {

        $arItem = new ItemRecord();
        $Mov = new MovimientosRecord();
        $Mov = MovimientosRecord::finder()->with_Concepto()->findByPk($IdMovimiento);
        $Tercero = new TercerosRecord();
        $Tercero = TercerosRecord::finder()->findByPk($Mov->IdTercero);
        $Configuraciones = new ConfiguracionesRecord();
        $Configuraciones = ConfiguracionesRecord::DevConfiguraciones();
        funciones::CrearDetallesKit($IdMovimiento, $Mov->IdDocumento);
        funciones::ValidarEscalasPreciosMov($IdMovimiento);
        $MovDet = new MovimientosDetRecord();
        $MovDet = MovimientosDetRecord::finder()->findAllBy_IdMovimiento($IdMovimiento);
        $NroReg = count($MovDet);
        $AplicaIvaAnterior = 0;
        if ($Mov->IdContrato != NULL) {
            $Contrato = new ContratosRecord();
            $Contrato = ContratosRecord::finder()->findByPk($Mov->IdContrato);
            if (count($Contrato) > 0) {
                $AplicaIvaAnterior = $Contrato->AplicaIvaAnterior;
            }
        }


        $Precio = false;
        $Doc = DocumentosRecord::finder()->findByPk($Mov->IdDocumento);
        if ($Doc->AfectaPrecios == 1) {
            $Precio = true;
        }



        $i = 0;
        $SubTotalFinal = 0;
        $TotalFinal = 0;
        $DctoFinal = 0;
        $IvaFinal = 0;
        $RteFuente = 0;
        $RteIva = 0;
        $douRteCree = 0;
        $CostoActual = 0;
        while ($i < $NroReg) {
            $Cantidad = $MovDet[$i]->Cantidad;
            $PorDcto = $MovDet[$i]->CantDescuento;
            $VrPrecio = $MovDet[$i]->Precio;

            if ($Precio == true) { // Precio.
                $Costo = $MovDet[$i]->Precio;
                $CostoActual = funciones::CostoActualVigDetalleMov($MovDet[$i]->IdMovimientoDet, 1);
                if ($Costo < 1) {
                    $Costo = '0.01';
                    $VrPrecio = $Costo;
                }
            } else { // Costo.
                $Costo = $MovDet[$i]->Costo;
                if ($Mov->IdDocumento == 87 && $Mov->IdConcepto == 179) {
                    $Costo = $MovDet[$i]->DiferenciaCostoNC;
                }
                $CostoActual = funciones::CostoActualVigDetalleMov($MovDet[$i]->IdMovimientoDet, 2);
                $Dcto = (($Costo * $PorDcto) / 100); // por unidad
                $CostoActual = $Costo - $Dcto;
            }

            //Se redondea para que e subtotal quede tal cual esta con el precio, diego y sandra./26/07/2018
            if ($Mov->IdDocumento != 87 && $Mov->IdConcepto != 179) {
                $Costo = round($Costo, 2);
            }

            if ($Tercero->ExentoIva == 1 && $MovDet[$i]->ExentoIVA == 1) {
                $PorIva = 0;
            } else {
                if ($AplicaIvaAnterior == 0 || $MovDet[$i]->PorIva == 0) {
                    $PorIva = $MovDet[$i]->PorIva;
                } else {
                    $PorIva = $Configuraciones->PorcentajeIvaAnterior;
                }
                if ($PorIva == '') {//Se realiza la validacion para que no saque error en el update.
                    $PorIva = 0;
                }
            }


            //Dcto
            $Dcto = (($Costo * $PorDcto) / 100); // por unidad
            $TotalDcto = $Cantidad * $Dcto;
            $DctoFinal = $DctoFinal + $TotalDcto;



            //subtotal.
            $SubTotal = ($Costo * $Cantidad) - $TotalDcto;
            //Validamos los documentos cuyo concepto sea por dif en $
            if ($Mov->IdConcepto > 0 && $Mov->Concepto->Opcion == 2) {
                $SubTotal = ($SubTotal * $MovDet[$i]->PorcentajeR);
                //Iva
                $TotalIva = ($SubTotal * $PorIva) / 100;
                $IvaFinal = $IvaFinal + $TotalIva;
            } else {
                //iva
                $TotalIva = ((($Costo - $Dcto) * $PorIva) / 100) * $Cantidad;
                $IvaFinal = $IvaFinal + $TotalIva;
            }

            if ($Mov->IdConcepto == 179) {
                $SubTotal = ($Costo * $Cantidad);
            }

            $SubTotalFinal = $SubTotalFinal + $SubTotal;

            //total
            $Total = $SubTotal + $TotalIva;
            $TotalFinal = $TotalFinal + $Total;

            $arItem = ItemRecord::finder()->FindByPk($MovDet[$i]->Id_Item);

            $strSql = "UPDATE movimientos_det SET PorIva= " . ($PorIva) . ", Total=" . ($Total) . ", SubTotal=" . ($SubTotal) . ", Precio=" . ($VrPrecio) . ","
                    . " TotalIva=" . ($TotalIva) . ", TotalDescuento=" . ($TotalDcto) . ","
                    . "CostoMvtoVig = if(CostoMvtoVig <=0 OR CostoMvtoVig is Null,'" . $CostoActual . "',"
                    . "CostoMvtoVig) WHERE IdMovimientoDet=" . $MovDet[$i]->IdMovimientoDet;
            $objComando = MovimientosDetRecord::finder()->getDbConnection()->createCommand($strSql);
            $objComando->execute();
            if ($Mov->IdConcepto > 0 && $Mov->Concepto->Opcion > 0 && ($Mov->TpDocumento != 33 )) {
                $ItemC = ItemRecord::finder()->FindByPk($MovDet[$i]->Id_Item);
                $ListaC = ListaCostosProvDetRecord::finder()->FindByPk($ItemC->IdListaCostosDetItem);
                $CostoActual = ($CostoActual / ($ListaC->CantContenido <= 0 ? 1 : $ListaC->CantContenido));
                $strSqlcos = "UPDATE movimientos_det SET Costo= 0 ,CostoMvtoVig = " . $CostoActual . ",CostoPromedio=0  WHERE IdMovimientoDet=" . $MovDet[$i]->IdMovimientoDet;
                $objComando = MovimientosDetRecord::finder()->getDbConnection()->createCommand($strSqlcos);
                $objComando->execute();
            }
            $i = $i + 1;
        }

        $Mov->SubTotal = $SubTotalFinal;
        if ($Mov->IdDocumento == 1 || $Mov->IdDocumento == 21 || $Mov->IdDocumento == 45) {
            if ($Tercero->Autoretenedor == 0) {
                if ($SubTotalFinal >= $Configuraciones->BaseRteFuente)
                    $RteFuente = ($SubTotalFinal * $Configuraciones->PorRteFuente) / 100;
            }

            $Mov->Total = $TotalFinal + $Mov->VrOtros - ($Mov->VrRetencion + $RteFuente);
            $Mov->Costo = $SubTotalFinal;
        } else
            $Mov->Total = $TotalFinal + ($Mov->VrOtros + $Mov->VrFletes);

        //Liquidar la retencion en la fuente de las ventas
        if (($Mov->TpDocumento == 5 && $Tercero->RteFteVenta == 1) || ($Mov->IdDocumento == 8 && $Tercero->RteFteVenta == 1)) {
            if ($Tercero->RteFteVentaSinBase == 1)
                $RteFuente = ($SubTotalFinal * $Configuraciones->PorRteFuente) / 100;
            if ($SubTotalFinal >= $Configuraciones->BaseRteFuente)
                $RteFuente = ($SubTotalFinal * $Configuraciones->PorRteFuente) / 100;
        }

        //Cacular retencion Cree en las facturas //A partir del 2017 se llama auto renta y tiene base
        if ($Mov->TpDocumento == 5 && date('Y-m-d') >= '2013-09-01' && $SubTotalFinal >= $Configuraciones->BaseAutoRenta) {
            $douRteCree = ($SubTotalFinal * $Configuraciones->PorRteCree) / 100;
        }


        //Liquidar retencion de iva para las ventas, solo los grandes contribuyentes y entidades del estado nos retienen 50% iva
        if ($Mov->TpDocumento == 5 && ($Tercero->IdClasificacionTributaria == 5 || $Tercero->IdClasificacionTributaria == 3)) {
            //Validacion acordada con Luz Dary de que las devoluciones tambien validen la base
            if ($IvaFinal >= $Configuraciones->BaseRteIvaVentas) {
                $RteIva = ($IvaFinal * $Configuraciones->PorRteIva) / 100;
            }
        }


        $Mov->VrDcto = $DctoFinal;
        $Mov->VrIva = $IvaFinal;
        $Mov->VrRteFuente = $RteFuente;
        $Mov->VrRteIva = $RteIva;
        $Mov->VrRteCree = $douRteCree;
        $Mov->save();
        funciones::ValidarAfectadasMov($IdMovimiento);
        funciones::ValidarDatosInvimaProducto($IdMovimiento);
        //Liquidar el total de las entradas de almacen por compras por si tiene retenciones por descuentos financieros
        if ($Mov->IdDocumento == 1) {
            funciones::LiquidarOtrosEA($IdMovimiento);
        }
    }

    public static function Redondear($Numero, $Decimales) {
        $Factor = pow(10, $Decimales);
        $Total = round(($Numero * $Factor) / $Factor);
        return $Total;
    }

    public static function LiquidarCotizacion($IdCot, $Op = false) {
        funciones::CrearDetallesKit($IdCot, 2);
        $CotDet = new CotizacionesDetRecord();
        $CotDet = CotizacionesDetRecord::finder()->FindAllBy_IdCotizacion($IdCot);
        $SubTotalT = 0;
        $TotalDctoT = 0;
        $TotalIvaT = 0;
        $TotalT = 0;
        $TotalProyectado = 0;
        $TotalDctoCliente = 0;
        $TotalCostoProyectado = 0;
        $TotalProyectadoSinIva = 0;
        foreach ($CotDet as $Detalle) {
            $TotalCliente = 0;
            $TotalCostoCliente = 0;
            $TotalClienteSinIva = 0;
            $CotDetAct = new CotizacionesDetRecord();
            $CotDetAct = CotizacionesDetRecord::finder()->FindByPk($Detalle->IdCotizacionDet);
            $douMargen = 1 - ($CotDetAct->Margen / 100);
            if ($douMargen == 0)
                $Precio = 0;
            else {
                if ($Op == true) {
                    $Precio = $CotDetAct->PrecioCotizacion;
                } else {
                    $Precio = $CotDetAct->CostoCotizacion / $douMargen;
                    $Precio = $Precio - (($Precio * $CotDetAct->DctoCotizacion) / 100);
                }
            }
            if ($CotDetAct->Redondeo != 0) {
                $Precio = round($Precio);
                $Modulo = $Precio % $CotDetAct->Redondeo;
                $Precio = $Precio - $Modulo;
                $CotDetAct->PrecioCotizacion = round($Precio);
            } else
                $CotDetAct->PrecioCotizacion = $Precio;

            //Verificado con Elisabeth dice que la dian no aplica descuento por lo tanto el iva va despues
            $SubTotal = $CotDetAct->CantidadCotizacion * $CotDetAct->PrecioCotizacion;
            //El precio ya tiene el descuento
            //$TotalDcto = ($SubTotal * $CotDetAct->DctoCotizacion) / 100; 
            $TotalDcto = 0;
            $TotalIva = ($SubTotal * $CotDetAct->PorIvaCotizacion) / 100;

            $TotalIvaT = $TotalIvaT + $TotalIva;
            $SubTotalT = $SubTotalT + $SubTotal;
            $TotalDctoT = $TotalDctoT + $TotalDcto;
            $Total = $SubTotal + $TotalIva;

            if ($Detalle->Consumo != 0 && $Detalle->FactorCliente != 0) {
                $douCantidadCliente = $Detalle->Consumo / $Detalle->FactorCliente;
                $douSubTotalCliente = $douCantidadCliente * $CotDetAct->PrecioCotizacion;
                $douSubTotalCostoCliente = $douCantidadCliente * $CotDetAct->CostoCotizacion;
                //El precio ya tiene el descuento
                //$TotalDctoCliente = ($douSubTotalCliente * $CotDetAct->DctoCotizacion) / 100;                
                $TotalIvaCliente = ($douSubTotalCliente * $CotDetAct->PorIvaCotizacion) / 100;
                $TotalCliente = $douSubTotalCliente + $TotalIvaCliente;
                $TotalCostoCliente = $douSubTotalCostoCliente;
                $TotalClienteSinIva = $douSubTotalCliente;
            }

            $CotDetAct->SubTotal = $SubTotal;
            $CotDetAct->TotalIva = $TotalIva;
            $CotDetAct->TotalDcto = $TotalDcto;
            $CotDetAct->Total = $Total;



            /*
             *  Para no aplicar dos veces el dcto, Sumamos al total de la cotizacion
             *  el descuento aplicado a cada item anteriormente. 
             */
            $TotalT = $TotalT + $Total;
            $TotalProyectado = $TotalProyectado + $TotalCliente;
            $TotalCostoProyectado = $TotalCostoProyectado + $TotalCostoCliente;
            $TotalProyectadoSinIva = $TotalProyectadoSinIva + $TotalClienteSinIva;

            $CotDetAct->save();
        }

        $Cot = new CotizacionesRecord;
        $Cot = CotizacionesRecord::finder()->FindByPk($IdCot);
        $Cot->SubTotal = $SubTotalT;
        $Cot->VrIva = $TotalIvaT;
        $Cot->VrDescuento = $TotalDctoT;
        $Cot->Total = $TotalT;
        $Cot->TotalProyectado = $TotalProyectado;
        $Cot->TotalCostoProyectado = $TotalCostoProyectado;

        if ($TotalProyectadoSinIva != 0) {
            $Cot->UtilidadProyectada = ($TotalProyectadoSinIva - $Cot->TotalCostoProyectado) / $TotalProyectadoSinIva;
        }

        $Cot->save();
    }

    public static function LiquidarSolicitudCotizacion($IdSolicitud) {


        $SolCotDet = new CotizacionesSolicitudesDetRecord();
        $SolCotDet = CotizacionesSolicitudesDetRecord::finder()->FindAllBy_IdSolicitud($IdSolicitud);
        $SubTotalT = 0;
        $TotalDctoT = 0;
        $TotalIvaT = 0;
        $TotalT = 0;
        $TotalProyectado = 0;
        $TotalDctoCliente = 0;
        foreach ($SolCotDet as $Detalle) {

            $TotalCliente = 0;
            $SolCotDetAct = new CotizacionesSolicitudesDetRecord();
            $SolCotDetAct = CotizacionesSolicitudesDetRecord::finder()->FindByPk($Detalle->IdSolicitudDet);
            $douMargen = 1 - ($SolCotDetAct->Margen / 100);
            if ($douMargen == 0)
                $Precio = 0;
            else {
                $Precio = $SolCotDetAct->CostoCotizacion / $douMargen;
                $Precio = $Precio - (($Precio * $SolCotDetAct->DctoCotizacion) / 100);
            }
            if ($SolCotDetAct->Redondeo != 0) {
                $Precio = round($Precio);
                $Modulo = $Precio % $SolCotDetAct->Redondeo;
                $Precio = $Precio - $Modulo;
                $SolCotDetAct->PrecioCotizacion = round($Precio);
            } else {
                $SolCotDetAct->PrecioCotizacion = $Precio;
            }

            //Verificado con Elisabeth dice que la dian no aplica descuento por lo tanto el iva va despues
            $SubTotal = $SolCotDetAct->CantidadCotizacion * $SolCotDetAct->PrecioCotizacion;
            //El precio ya tiene el descuento
            //$TotalDcto = ($SubTotal * $CotDetAct->DctoCotizacion) / 100; 
            $TotalDcto = 0;
            $TotalIva = ($SubTotal * $SolCotDetAct->PorIvaCotizacion) / 100;

            $TotalIvaT = $TotalIvaT + $TotalIva;
            $SubTotalT = $SubTotalT + $SubTotal;
            $TotalDctoT = $TotalDctoT + $TotalDcto;
            $Total = $SubTotal + $TotalIva;

            if ($Detalle->Consumo != 0 && $Detalle->FactorCliente != 0) {
                $douCantidadCliente = $Detalle->Consumo / $Detalle->FactorCliente;
                $douSubTotalCliente = $douCantidadCliente * $SolCotDetAct->PrecioCotizacion;
                //El precio ya tiene el descuento
                //$TotalDctoCliente = ($douSubTotalCliente * $CotDetAct->DctoCotizacion) / 100;                
                $TotalIvaCliente = ($douSubTotalCliente * $SolCotDetAct->PorIvaCotizacion) / 100;
                $TotalCliente = $douSubTotalCliente + $TotalIvaCliente;
            }

            $SolCotDetAct->SubTotal = $SubTotal;
            $SolCotDetAct->TotalIva = $TotalIva;
            $SolCotDetAct->TotalDcto = $TotalDcto;
            $SolCotDetAct->Total = $Total;


            /*
             *  Para no aplicar dos veces el dcto, Sumamos al total de la cotizacion
             *  el descuento aplicado a cada item anteriormente. 
             */
            $TotalT = $TotalT + $Total;
            $TotalProyectado = $TotalProyectado + $TotalCliente;


            $SolCotDetAct->save();
        }

        $SolCot = new CotizacionesSolicitudesRecord();
        $SolCot = CotizacionesSolicitudesRecord::finder()->FindByPk($IdSolicitud);
        $SolCot->SubTotal = $SubTotalT;
        $SolCot->VrIva = $TotalIvaT;
        $SolCot->VrDescuento = $TotalDctoT;
        $SolCot->Total = $TotalT;
        $SolCot->TotalProyectado = $TotalProyectado;
        $SolCot->save();
    }

    /**
     * Toma los valores de las retenciones hechas a un documento
     * y cacula el <b> Total a pagar</b> de del documento segun las retenciones realizadas a este.
     * @param integer $intIdMovimiento 
     * */
    public static function LiquidarOtrosEA($intIdMovimiento) {

        $arMovimiento = MovimientosRecord::finder()->findByPk($intIdMovimiento);
        $arMovimiento->VrRetencion = 0;
        $arMovimiento->Total = 0;

        $arRetMovimiento = Movimientos_RetencionesRecord::finder()->findAllBy_IdMovimiento($intIdMovimiento);

        for ($i = 0; $i < count($arRetMovimiento); $i++) {
            $MovRetAct = Movimientos_RetencionesRecord::finder()->findByPk($arRetMovimiento[$i]->IdRetencion);
            $MovRetAct->BaseRetencion = $arMovimiento->SubTotal;
            $ConRet = ConceptosRetencionesRecord::finder()->findByPk($MovRetAct->IdConcepto);
            $Retencion = ($arMovimiento->SubTotal * $ConRet->PorConceptoRetencion) / 100;

            $MovRetAct->VrRetencion = $Retencion;

            $MovRetAct->save();


            $arMovimiento->VrRetencion = $arMovimiento->VrRetencion + $Retencion;
        }

        $arMovimiento->Total = ($arMovimiento->SubTotal + $arMovimiento->VrOtros + $arMovimiento->VrIva) - ($arMovimiento->VrRetencion + $arMovimiento->VrRteFuente);
        $arMovimiento->save();
    }

    Public static function boolNumber($bValue = false) {                      // returns integer
        return ($bValue ? 1 : 0);
    }

    Public static function boolString($bValue = false) {                      // returns string
        return ($bValue ? 'true' : 'false');
    }

    /**
     * @param Id del item.
     * @return si el item aafecta inventario o no (1 o 0).
     * */
    public static function ItemDet($IdItem) {
        $Item = new ItemRecord;
        $Item = ItemRecord::finder()->findByPk($IdItem);
        $Datos = $Item->AfectaInventario;
        return $Datos;
    }

    /**
     * Modifica la cantidad de veces que ha sido impreso un documento.
     * @param int $IdMov => El id del movimiento.
     * @param string $strUsuarioActivo usuario que esta activo para crear el log
     * */
    public static function CtrImpresion($IdMov, $strUsuarioActivo) {
        $Mov = new MovimientosRecord();
        $Mov = MovimientosRecord::finder()->FindByPk($IdMov);
        if ($Mov->IdDocumento != 3) {
            funciones::CrearLog(7, $IdMov, $strUsuarioActivo);
        }
        if ($Mov->Impresion <= 0) {//Se crea esta validacion para que no llegue correo de copias.
            funciones::NotificarAccion($Mov->IdDocumento, 7, $IdMov, $Mov->IdAutoriza);
            funciones::CrearLog(7, $IdMov, $strUsuarioActivo);
        }
        $sql = "UPDATE movimientos SET Impresion=Impresion+1 WHERE IdMovimiento = $IdMov";
        $command = MovimientosRecord::finder()->getDbConnection()->createCommand($sql);
        $command->execute();
    }

    /**
     * Modifica la cantidad de veces que ha sido impreso un Reporte.
     * @param int $IdMov => El id del movimiento.
     * @param string $strUsuarioActivo usuario que esta activo para crear el log
     * */
    public static function CtrImpresionReporte($IdRep, $strUsuarioActivo) {
        $Rep = ReporteServiciosRecord::finder()->FindByPk($IdRep);
        funciones::CrearLog(7, $IdRep, $strUsuarioActivo);
//        if ($Mov->Impresion <= 0) {//Se crea esta validacion para que no llegue correo de copias.
//            funciones::NotificarAccion($Mov->IdDocumento, 7, $IdMov, $Mov->IdAutoriza);
//        }
        $sql = "UPDATE reporte_servicios SET Impresion=Impresion+1 WHERE IdReporteServicio = $IdRep";
        $command = ReporteServiciosRecord::finder()->getDbConnection()->createCommand($sql);
        $command->execute();
    }

    /**
     * Modifica la cantidad de veces que ha sido impreso un Reporte.
     * @param int $IdMov => El id del movimiento.
     * @param string $strUsuarioActivo usuario que esta activo para crear el log
     * */
    public static function CtrImpresionReporteNovedad($IdRepNov, $strUsuarioActivo) {
        $Rep = ReporteNovedadesRecord::finder()->FindByPk($IdRepNov);
//        funciones::CrearLog(7, $IdRepNov, $strUsuarioActivo);
//        if ($Mov->Impresion <= 0) {//Se crea esta validacion para que no llegue correo de copias.
//            funciones::NotificarAccion($Mov->IdDocumento, 7, $IdMov, $Mov->IdAutoriza);
//        }
        $sql = "UPDATE reporte_novedades SET Impresion=Impresion+1 WHERE IdReporteNovedad = $IdRepNov";
        $command = ReporteNovedadesRecord::finder()->getDbConnection()->createCommand($sql);
        $command->execute();
    }

    /**
     * Valida que la direccion seleccionada en un documento corresponda al tercero
     * que se dice pertenecer.
     * @param int IdTercero
     * @param inf IdDireccion
     * @return boolean
     * */
    public static function ValidarDireccion($IdTercero, $IdDireccion) {
        $Ok = true;
        $Direccion = new DireccionesRecord;
        $Direccion = DireccionesRecord::finder()->findByPk($IdDireccion);

        if ($Direccion->IdTercero != $IdTercero)
            $Ok = false;

        return $Ok;
    }

    public static function DevSiNo($Valor) {
        if ($Valor == 1) {
            return "SI";
            ;
        } else {
            return "NO";
            ;
        }
    }

    /**
     * Busca la informaicion perteneciente a un tercero buscado por 
     * identificacion o por nombre, y retorna los datos en forma de lista al autocomplete desde el que 
     * este metodo es llamado.
     * @param type $sender
     * @param type $param
     * @param type $Todos Variable que determina si muestra un listado de terceros incluyendo los inactivos 
     * o solo los activos, por defecto solo los que estan activos.
     * @example en caso de intentar crear un documento nuevo, solo muestra los activos, para consultas y estadisticas 
     * los muestra todos.
     * */
    public static function BuscarTercero($sender, $param, $Todos = '', $inactivo = false, $ObjVista = '') {
        $sql = "SELECT NombreCorto,IdTercero 
                FROM terceros
                WHERE IdTercero != 0 and (NombreCorto LIKE '%" . $sender->Data . "%' OR Identificacion LIKE '%" . $sender->Data . "%')";

        if ($Todos != '' && $inactivo == true) {
            $sql = $sql . " AND 1";
        } else if ($inactivo == true) {
            $sql = $sql . " AND 1";
        } else if ($Todos != '') {
            $sql = $sql . "AND Inactivo = 0";
        }

        if (!empty($ObjVista)) {
            if ($ObjVista->User->Tipo == 3) {
                $IdAsesor = funciones::ObtenerIDAsesor($ObjVista->User->Name, 1);
                if ($IdAsesor != 0) {
                    $sql = $sql . " AND terceros.IdAsesor =" . $IdAsesor;
                }
            }
        }

        $sql = $sql . " LIMIT 5";
        $terceros = TercerosRecord::finder()->findAllBySql($sql);
        if (count($terceros) <= 0) {
            $sql = "select Nombres as NombreCorto,IdTercero from usuarios where (Nombres Like'%" . $sender->Data . "' or IdTercero Like'%" . $sender->Data . "') limit 5";
            $terceros = TercerosRecord::finder()->FindAllBySql($sql);
        }
        $list = array();

        foreach ($terceros as $row)
            $list[] = $row->NombreCorto . "_" . $row->IdTercero;

        $sender->setDataSource($list);
        $sender->dataBind();
    }

    /**
     * 
     * @param type $sender
     * @param type $param
     * @param type $usuario
     * @return Datos encontrados
     * FhActualizacion : 05/04/2017
     */
    public static function BuscarTerceroAsesor($sender, $param, $usuario, $ObjVista = '') {
        $sql = "SELECT NombreCorto,terceros.IdTercero 
                FROM terceros
                LEFT JOIN asesores on asesores.IdAsesor = terceros.IdAsesor
                LEFT JOIN usuarios on usuarios.Usuario = asesores.UsuarioAsesor
                WHERE usuarios.Usuario = '" . $usuario . "' and terceros.IdTercero != 0 and (NombreCorto LIKE '%" . $sender->Data . "%' OR terceros.Identificacion LIKE '%" . $sender->Data . "%')";

        if (!empty($ObjVista)) {
            if ($ObjVista->User->Tipo == 3) {
                $IdAsesor = funciones::ObtenerIDAsesor($ObjVista->User->Name, 1);
                if ($IdAsesor != 0) {
                    $sql = $sql . " AND terceros.IdAsesor =" . $IdAsesor;
                }
            }
        }
        $sql = $sql . " LIMIT 5";
        $terceros = TercerosRecord::finder()->findAllBySql($sql);

        $list = array();
        if (count($terceros) > 0) {
            foreach ($terceros as $row) {
                $list[] = $row->NombreCorto . "_" . $row->IdTercero;
            }
        } else {
            $list[] = 'No se encontro terceros asociados a ti con el nombre ingresado.';
        }

        $sender->setDataSource($list);
        $sender->dataBind();
    }

    /**
     * Busca la direccion, el Asesor, la forma de pago de un tercero.
     * y asigna los valores encontrados a los controles respecitivos en el formulario 
     * desde el cual es invocado el metodo
     * @param $objVista => Es el objeto de la vista actual(pagina actual)
     * */
    public static function BuscarDatos($objVista) {
        if ($objVista->TxtIdTercero->Text != '' && is_numeric($objVista->TxtIdTercero->Text)) {
            $sql = "SELECT IdDireccion, CONCAT(direcciones.NmDireccion,' - TP: ',tipos_direcciones.NmTipoDireccion) as NmDireccion 
                    FROM direcciones
                    LEFT JOIN tipos_direcciones ON tipos_direcciones.IdTipoDireccion = direcciones.Tipo
                    WHERE (direcciones.IdTercero=" . $objVista->TxtIdTercero->Text . " AND direcciones.Inactiva = 0) ORDER BY NmDireccion";

            $direccion = new DireccionesRecord;
            $direccion = DireccionesRecord::finder()->findAllBySql($sql);

            if (count($direccion) <= 0) {
                funciones::Mensaje("No existen direcciones para este cliente o se encuentran inactivas.", 2, $objVista);
            } else {
                $objVista->Cbo_Direccion->DataSource = $direccion;
                $objVista->Cbo_Direccion->dataBind();
            }


            $DatosTercero = new TercerosRecord;
            $sql = "SELECT IdAsesor, IdFormaPago, IdCondicionEntrega FROM terceros WHERE IdTercero=" . $objVista->TxtIdTercero->Text;
            $DatosTercero = TercerosRecord::finder()->findBySql($sql);

            if (isset($_REQUEST['ctl0$Main$Cbo_Asesores'])) {
                $Asesor = new AsesoresRecord;
                $Asesor = AsesoresRecord::finder()->findByPk($DatosTercero->IdAsesor);
                $objVista->Cbo_Asesor->SelectedValue = $Asesor->IdAsesor;
            }

            if (isset($_REQUEST['ctl0$Main$Cbo_CondEntrega'])) {
                $CondEntrega = CondicionesEntregaRecord::finder()->findByPk($DatosTercero->IdCondicionEntrega);
                $objVista->Cbo_CondEntrega->SelectedValue = $CondEntrega->IdCondicionEstrega;
            }

            if (isset($_REQUEST['ctl0$Main$Cbo_FormaPago'])) {
                $FormaPago = new FormasPagoRecord;
                $FormaPago = FormasPagoRecord::finder()->findByPk($DatosTercero->IdFormaPago);
                $objVista->Cbo_FormaPago->SelectedValue = $FormaPago->IdFormaPago;
                $objVista->TxtSoporte->Focus();
            }
        }
    }

    /**
     * Separa el nit(o identificacion) y el nombre de un tercero en el autocomplete
     * cuando el usuario selecciona una de las sugerencias que arroja el metodo "Buscar Tercero"
     * 
     * @param $object $objVista Es el objeto de la vista actual(pagina actual)
     * 
     * */
    public static function CallBack($objVista) {
        $Datos = explode("_", $objVista->TxtIdTercero->Text);
        $objVista->TxtIdTercero->Text = $Datos[1];
        $objVista->TxtNombre->Text = $Datos[0];
    }

    /**
     * Verifica si el usuario tiene permiso(especial) para realizar determinada operacion.
     * @param string $strUsuario => Nombre del usuario del que se desea corroborar el permiso.
     * @param int $IdPermiso => Id del permiso de la tabla permisosespeciales.
     * @return boolean que determina si tiene o no permiso.
     * */
    public static function PermisosConsultas($strUsuario, $intIdPermiso) {
        // Si el usuario no es administrador verificamos los permisos que tiene.
        if (self::IsAdmin($strUsuario) == false) {

            $PermisoEspecial = new PermisosConsultasRecord();
            $PermisoEspecial = PermisosConsultasRecord::finder()->findBy_IdUsuario_AND_IdPermisoEspecial(strtolower($strUsuario), $intIdPermiso);

            if (Count($PermisoEspecial) != 0) {
                if ($PermisoEspecial->Ver == 1)
                    $PermisoEspecial = true;
                else
                    $PermisoEspecial = false;
            } else
                $PermisoEspecial = false;
        }
        else {
            $PermisoEspecial = true;
        }

        return $PermisoEspecial;
    }

    /**
     * verifica si el usuario tiene los permisos para realizar determinada operacion en los cruds.
     * @param int $IdPermiso Id del permiso de la tabla permisos especiales.
     * @param string $strUsuario Nombre del usuario actual
     * @return boolean que determina si tiene o no permiso.
     * */
    public static function PermisosCrud($strUsuario, $IdPermiso) {
        $PermisoEspecial = false;
        $PermisoEspecial = new PermisosCrudRecord();
        if (funciones::IsAdmin($strUsuario) == false) {
            $PermisoEspecial = PermisosCrudRecord::finder()->findByPk(strtolower($strUsuario), $IdPermiso);
        } else {
            $PermisoEspecial->Ver = 1;
            $PermisoEspecial->Crear = 1;
            $PermisoEspecial->Editar = 1;
            $PermisoEspecial->Eliminar = 1;
        }

        return $PermisoEspecial;
    }

    /**
     * Recibe el numero del movimiento y retorna la fecha y la hora de impresion de un documento.
     * @param <integer> $IdMov
     * @return <string> Re
     * */
    public static function FhImpDoc($IdMov) {
        $Datos = 'Fecha y hora de impresion: ' . date('Y-m-d H:i') . ", Mov: " . $IdMov;
        return $Datos;
    }

    public static function FhImpRep($IdRep) {
        $Datos = 'Fecha y hora de impresion: ' . date('Y-m-d H:i') . ", Rep: " . $IdRep;
        return $Datos;
    }

    /**
     * Determina si un usuario es o no admistrador.
     * @param string $strUsuario Nombre del usuario del que se desea corroborar el permiso.
     * @return boolean $Admin Retorna True si el usuario tiene perfil de administrador False en caso contrario.
     * */
    public static function IsAdmin($strUsuario) {

        $Admin = false;
        $arUsuario = new UsuarioRecord;
        $arUsuario = UsuarioRecord::finder()->findByPk($strUsuario);

        if ($arUsuario->Tipo == 1)
            $Admin = true;
        else
            $Admin = false;
        return $Admin;
    }

    /**
     * Convierte el objeto devuelto por una consulta de prado en un array asociativo.
     * http://www.tierra0.com/2007/09/18/pasa-los-datos-de-un-objeto-a-array-con-php/
     * */
    public static function object2array($valor) {
        $dato = NULL;
        if (!(is_array($valor) || is_object($valor))) { //si no es un objeto ni un array
            $dato = $valor; //lo deja
        } else { //si es un objeto
            foreach ($valor as $key => $valor1) { //lo conteo
                $dato[$key] = funciones::object2array($valor1); //
            }
        }
        return $dato;
    }

    /**
     * Forza la descarga de un documento digitalizado.
     * @param integer $IdCarga Directorio donde se almaceno el archivo.
     * @param string  $Opc Opcion del tipo de documento a descargar, D = Documento interno, C = Correspondencia.
     * @param objeto  $objVista La vista actual 
     * @param Objeto  $TpDoc Si es documentos anexos a mov o cot o documento general del doc.
     * FhActualizacion : 22/08/2017.
     * */
    public static function VerDocumento($IdCarga, $Opc, $objVista, $TpDoc = 1) {
        if ($TpDoc == 1) {
            $Archivo = DocDigitalizadosRecord::finder()->findByPk($IdCarga);
            $configuracion = ConfiguracionesRecord::finder()->findAll();
            // Si intentan abrir un documento,
            if ($Opc == 'D')
                $Direccion = $configuracion[0]->DireccionDocumentos;
            else
                $Direccion = $configuracion[0]->DireccionDirectoriosC;

            if (count($Archivo) != 0) {
                // Verifica si el archivo existe, si existe forza la descarga.
                if (file_exists($Direccion . "/" . $Archivo->IdDirectorio . "/" . $Archivo->IdArchivo)) {
                    $Arc = $Archivo->TituloDoc;

                    $filename1 = $Direccion . "/" . $Archivo->IdDirectorio . "/" . $Archivo->IdArchivo;
                    $Row = mime_content_type($filename1);
                    $TipoArchivo = funciones::ValidarTipoArchivo($Row);
                    $RowPart = explode("-", $TipoArchivo);
                    header("Expires: -1");
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
                    header("Content-type: " . $RowPart[1] . "\n"); //or yours?
                    header("Content-Transfer-Encoding: binary");
                    header("Cache-Control: no-store, no-cache, must-revalidate");
                    header("Cache-Control: post-check=0, pre-check=0");
                    header("Pragma: no-cache");
                    $len = filesize($filename1);
                    header("Content-Length: $len;\n");
                    $outname = str_replace(',', '-', $Arc);
                    header("Content-Disposition: attachment; filename=" . $outname . "." . $RowPart[0] . ";\n\n");
                    readfile($filename1);
                } else {
                    funciones::Mensaje("El archivo del documento no existe o se ha movido, consulte con el administrador del sistema.", 2, $objVista);
                    return false;
                }
            } else {
                funciones::Mensaje("No hay ningun registro de este documento.", 2, $objVista);
                return false;
            }
        } else if ($TpDoc == 2) {
            $arDocumentosDigitalizados = new DocumentosDigitalizadosRecord();
            $arDocumentosDigitalizados = DocumentosDigitalizadosRecord::finder()->FindByPk($IdCarga);

            $Configuraciones = new ConfiguracionesRecord();
            $Configuraciones = ConfiguracionesRecord::DevConfiguraciones();

            $Ruta = $Configuraciones->DireccionDocumentosDigitalizados . DIRECTORY_SEPARATOR . $arDocumentosDigitalizados->IdDirectorio . DIRECTORY_SEPARATOR . $arDocumentosDigitalizados->IdArchivo;
            if (count($arDocumentosDigitalizados) > 0) {
                // Verifica si el archivo existe, si existe forza la descarga.
                if (file_exists($Ruta)) {
                    header('Content-type: application/pdf');
                    header('Content-Disposition: attachment; filename=' . $arDocumentosDigitalizados->NmArchivo);
                    readfile($Ruta);
                } else
                    funciones::Mensaje("El archivo del documento no existe o se ha movido, consulte con el administrador del sistema.", 2, $objVista);
            } else
                funciones::Mensaje("No hay ningun registro de este documento.", 2, $objVista);
        }
    }

    /**
     * recibe y cambia el formato de una fecha.
     * @param date Fecha en formato YYYY-mm-dd
     * @return date Fecha en formato YYYY/mm/dd
     */
    public static function ForFecha($Fecha) {
        try {
            list($Anio, $Mes, $Dia) = explode("-", $Fecha);
            return $Anio . "/" . $Mes . "/" . substr($Dia, 0, 2);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * recibe y cambia el formato de una fecha.
     * @param date Fecha en formato YYYY-mm-dd
     * @return date Fecha en formato nacional dd/mm/YYYY
     */
    public static function FormatoFecha($Fecha) {
        list($Anio, $Mes, $Dia) = explode("-", $Fecha);
        return substr($Dia, 0, 2) . "/" . $Mes . "/" . $Anio;
    }

    /**
     * Establece un titulo para la pagina actual segun el tipo de accion.
     * 1- Listado General
     * 2- Nuevo
     * 3- Editar
     * 4- Detalles
     * 5- Editar Detalles
     * 
     * @param integer $intTpTitulo Tipo de titulo a establecer
     * @param integer $intIdDocumento id del documento
     * @param object $objVista paso de la presentacion para utilizar los objetos de esta
     */
    public static function EstableceTituloPagina($intTpTitulo, $intIdDocumento, $objVista) {
        $Documento = DocumentosRecord::finder()->findByPk($intIdDocumento);
        $NombreDoc = $Documento->Nombre;
        switch ($intTpTitulo) {
            // Listado general
            case 1 :
                $objVista->Title = "Listado de " . $NombreDoc . " - Kasten";
                break;

            // Nuevo Documento
            case 2 :
                $objVista->Title = "Nuevo - " . $NombreDoc . " - Kasten";
                break;

            // Editar Documento
            case 3 :
                $objVista->Title = "Editar " . $NombreDoc . " - Kasten";
                break;

            // Detalles
            case 4 :
                $objVista->Title = "Detalles de " . $NombreDoc . " - Kasten";
                break;

            // Editar Detalles
            case 5 :
                $objVista->Title = "Editar detalles de " . $NombreDoc . " - Kasten";
                break;
        }
    }

    /**
     * Devuelve el estado del alistamiento segun el tipo en BD
     * @param Alistamiento numero
     * @return Estado del alistamiento
     */
    public static function DevEstadoAlistamiento($Alistamiento) {

        switch ($Alistamiento) {
            case 0 :
                return "SIN ALISTAMIENTO";
                break;

            case 1 :
                return "EN PROCESO DE ALISTAMIENTO";
                break;

            case 2 :
                return "ALISTAMIENTO TERMINADO";
                break;
        }
    }

    /**
     * Adiciona ceros a la izquierda del numero tantos como haga falta para que la cadena a retornar
     * tenga 11 caracteres.
     * @param $NroCr => Numero de caracteres que debe tener la cadena a retornar.
     * @param $Str => El caracter que se utilizara para rellenar la cadena.
     * @param <Int> $Nro => Numero del documento o nit del tercero.
     * @return <String> $Nro => Numero del documento completado con ceros a su izquierda para
     * el formato de i limitada.
     * */
    public static function RellenarNr($Nro, $Str, $NroCr) {
        $Longitud = strlen($Nro);

        $Nc = $NroCr - $Longitud;
        for ($i = 0; $i < $Nc; $i++)
            $Nro = $Str . $Nro;

        return (string) $Nro;
    }

    /**
     * Comprobar el saldo de la cartera del tercero para un movimiento
     * @param $IdMovimiento => IdMovimiento del documento que se esta realizando
     * @param $strUsuarioActivo => Usuario Activo para comprobar si tiene permiso
     * @return <Bool> Verdadero o falso si el cliente tiene problemas de cupo
     * */
    public static function CpCupoCartera($IdMovimiento, $objVista) {

        $Mov = MovimientosRecord::finder()->with_Tercero()->FindByPk($IdMovimiento);
        // Buscando Anticipos Cliente
        $strSql = "SELECT 
                   (SUM(cuentas_por_cobrar.Saldo) * -1) as Saldo 
                   FROM cuentas_por_cobrar 
                   INNER JOIN terceros ON terceros.IdTercero = cuentas_por_cobrar.IdTercero
                   WHERE cuentas_por_cobrar.IdDocumento = 64 
                   AND cuentas_por_cobrar.Saldo < 0 AND cuentas_por_cobrar.IdTercero = " . $Mov->IdTercero;
        $Anticipos = CuentasCobrarRecord::finder()->findBySql($strSql);
        $VrAnticipos = $Anticipos->Saldo;

        if ($Mov->Tercero->IdFormaPago == 2) {
            $VrCojin = ($Mov->Tercero->LimiteCreditoExtra * 0.05);
            if ((($Mov->Tercero->SaldoCartera - $VrAnticipos) + $Mov->Total) <= ($Mov->Tercero->LimiteCreditoExtra + $VrCojin)) {
                return true;
            } else {
                funciones::Mensaje("El saldo de cartera " . number_format($Mov->Tercero->SaldoCartera, 2, ',', '.') . " mas el valor de la factura " . number_format($Mov->Total, 2, ',', '.') . " superan el cupo de credito del cliente, por favor comuniquese con cartera o solicite una segunda autorización.", 2, $objVista);
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Comprobar si debe validar el promedio por bloqueo proximo a ejecutarse
     * @return <Bool> Verdadero o falso si el puede o no facturar
     * */
    public static function BloqueoProximoPromedio($IdTercero, $IdMovimiento) {

        $arConfiguraciones = ConfiguracionesRecord::DevConfiguraciones();

        if ($arConfiguraciones->ActivarBloqueoAutClientes == 1) {
            $arrayCuentas = CuentasCobrarRecord::BuscarCuentasBloqueoCliente('', $IdTercero);

            if (count($arrayCuentas) > 0) {
                $Movimiento = MovimientosRecord::finder()->with_Tercero()->FindByPk($IdMovimiento);
                $Promedio = MovimientosRecord::DevPromedioCompraSeisMeses($IdTercero);
                if ($Movimiento->Total > $Promedio) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public static function ActualizarPedCom($IdItem, $IdDocumento, $IdMov, $IdMovDet, $Accion) {
        $Movimiento = new MovimientosRecord();
        $Movimiento = MovimientosRecord::finder()->FindByPk($IdMov);

        $MovimientoDet = new MovimientosDetRecord();
        $MovimientoDet = MovimientosDetRecord::finder()->findByPK($IdMovDet);

        $Item = new ItemRecord();
        $Item = ItemRecord::finder()->findByPk($IdItem);

        if ($Movimiento->IdDocumento == 8) {
            if ($Accion == 1) {
                $CantAfectar = $MovimientoDet->Cantidad - $MovimientoDet->CantAfectada;
                $Item->CantPedido = $Item->CantPedido + $CantAfectar;
            } else {
                $CantAfectar = $MovimientoDet->Cantidad - $MovimientoDet->CantAfectada;
                $Item->CantPedido = $Item->CantPedido - $CantAfectar;
            }
        } elseif ($IdDocumento == 6) {
            $CantAfectar = $MovimientoDet->Cantidad - $MovimientoDet->CantAfectada;
            if ($Accion == 1) {
                $Item->CantOC = $Item->CantPedido + $CantAfectar;
            } else {
                $Item->CantOC = $Item->CantPedido - $CantAfectar;
            }
        } elseif ($IdDocumento == 1) {
            $CantAfectar = $MovimientoDet->Cantidad - $MovimientoDet->CantAfectada;
            if ($Accion == 1)
                $Item->CantOC = $Item->CantOC - $CantAfectar;
            else
                $Item->CantOC = $Item->CantOC + $CantAfectar;
        }
        elseif ($IdDocumento == 11 || $IdDocumento == 12) {
            if ($Accion == 1)
                $Item->CantPedido = $Item->CantPedido - ($MovimientoDet->Cantidad - $MovimientoDet->CantAfectada);
            else
                $Item->CantPedido = $Item->CantPedido + ($MovimientoDet->Cantidad - $MovimientoDet->CantAfectada);
        }
        elseif ($IdDocumento == 3) {
            if ($Accion == 1) {
                $Enlace = new MovimientosDetRecord();
                $Enlace = MovimientosDetRecord::finder()->findByPk($MovimientoDet->Enlace);
                $CantAfectar = $Enlace->Cantidad - $Enlace->CantAfectada;
                if ($Enlace->IdDocumento == 8) {
                    $Item->CantPedido = $Item->CantPedido - $CantAfectar;
                } else {
                    $Item->CantPedido = $Item->CantPedido + $CantAfectar;
                }
            }
        }
        $Item->save();

        RegenerarCantidades::RegenerarPedidos($IdItem);
        RegenerarCantidades::RegenerarCompras($IdItem);
    }

    /**
     * Buscar un item por iditem, descripcion o referencia
     * y los despliega en forma de lista de sugerencia.
     * */
    public static function BuscarItem($sender, $param) {
        $Item = new ItemRecord();
        $Item = ItemRecord::BuscarItem($sender->Data);

        $list = array();
        foreach ($Item as $Fila)
            $list[] = $Fila->Id_Item . "_" . $Fila->Descripcion;

        $sender->setDataSource($list);
        $sender->dataBind();

        // return $list;
    }

    public function BuscarItemLista($sender, $param, $Marca = '') {
        $Mov = new MovimientosRecord();
        $Mov = MovimientosRecord::finder()->FindByPk($this->Request['IdMov']);
        $Tercero = new TercerosRecord();
        $Tercero = TercerosRecord::finder()->FindByPk($Mov->IdTercero);
        $Item = new ItemRecord();
        $Item = ItemRecord::BuscarItemLista($sender->Data, $Tercero->IdListaPrecios, $Marca);

        $list = array();
        if (count($Item) > 0) {
            foreach ($Item as $Fila) {
                $list[] = $Fila->Id_Item . "_" . $Fila->Descripcion;
            }
        } else {
            $list[] = 'El item parece no existir o no pertenecer a la lista del cliente.';
        }
        $sender->setDataSource($list);
        $sender->dataBind();

        // return $list;
    }

    /**
     * Separa el codigo de item de la descripcion de un autocomplete
     * cuando esta es seleccionada por el usuario
     * @param objeto $objVista el objeto que contiene la vista actual.
     * @example 1010-CINTA DE ESMASCARAR
     * */
    public static function CallBackItem($objVista) {
        $Datos = explode("_", $objVista->TxtIdItem->Text);
        $objVista->TxtIdItem->Text = $Datos[0];
        $objVista->TxtDescripcion->Text = $Datos[1];
    }

    public static function suma_fechas($fecha, $ndias) {
        if (preg_match("/[0-9]{1,2}\/[0-9]{1,2}\/([0-9][0-9]){1,2}/", $fecha))
            list($dia, $mes, $año) = explode("/", $fecha);
        if (preg_match("/[0-9]{1,2}-[0-9]{1,2}-([0-9][0-9]){1,2}/", $fecha))
            list($dia, $mes, $año) = explode("-", $fecha);
        $nueva = mktime(0, 0, 0, $mes, $dia, $año) + $ndias * 24 * 60 * 60;
        $nuevafecha = date("Y-m-d", $nueva);
        return ($nuevafecha);
    }

    /**
     * Almacena un registro de la accion realizada en el modulo de listas de precios/costos.
     * @param <Integer> $IdListaPrecioDet => Id del detalle en la lista de precios/cotos
     * @param <Integer> $Accion Id de la accion realizada, la descripcion de la accion esta almacenada en la
     * tabla acciones.
     * */
    public static function LogListas($IdListaPrecioDet, $IdItem, $Accion, $Tipo) {
        $Log = new LogListasRecord();
        $Log->Fecha = date('Y-m-d H:i:s');
        $Log->IdAccion = $Accion;
        $Log->IdDetalle = $IdListaPrecioDet;
        $Log->Id_Item = $IdItem;
        $Log->Usuario = $this->User->Name;
        $Log->Tipo = $Tipo;
        $Log->save();
    }

    /**
     * Establece si un usuario tiene o no el permiso para cambiar la forma de pago de un cliente.
     * @param <type> $IdTercero
     * @return <type>
     * */
    public static function FormaPago($IdTercero, $IdPermiso, $objVista, $IsProv = false) {
        if ($objVista->Cbo_FormaPago->SelectedValue != "") {
            if (funciones::PermisosConsultas($objVista->User->Name, $IdPermiso) == true) {
                $FormaPago = $objVista->Cbo_FormaPago->SelectedValue;
            } else {
                $Tercero = TercerosRecord::finder()->findByPk($IdTercero);
                if ($IsProv == true) {
                    if ($Tercero->IdFormaPagoProveedor != NULL) {
                        $FormaPago = $Tercero->IdFormaPagoProveedor;
                    } else {
                        funciones::Mensaje("El proveedor no tiene parametrizada una forma de pago.", 2, $objVista);
                        $FormaPago = false;
                    }
                } else if ($Tercero->Cliente == 1) {
                    if ($Tercero->IdFormaPago != NULL) {
                        $FormaPago = $Tercero->IdFormaPago;
                    } else {
                        funciones::Mensaje("El cliente no tiene parametrizada una forma de pago.", 2, $objVista);
                        $FormaPago = false;
                    }
                } else {
                    funciones::Mensaje("Debe seleccionar una forma de pago para el tercero.", 2, $objVista);
                    $FormaPago = false;
                }
            }
        } else {
            funciones::Mensaje("Debe seleccionar una forma de pago para el tercero.", 2, $objVista);
            $FormaPago = false;
        }
        return $FormaPago;
    }

    /**
     * Calcula el margen de un detalle de cotizacion con base en el tipo de precio
     * sobre el que esta hecha la cotizacion.
     * <p>
     * Tipo 1 - Precio General,
     * Tipo 2 - Precio Especial,
     * Tipo 3 - Precio Licitacion,
     * </p>
     * @param integer $Tipo Tipo de precio de la cotizacion.
     * @param $objeto $ListaCostoDet Objeto con el registro del detalle de la lista de costos.
     * @return float $Margen el margen que tendra el detalle. 
     */
    public static function MargenCot($Tipo, $ListaCostoDet) {
        $Margen = 0;
        switch ($Tipo) {
            case 1:
                $Margen = $ListaCostoDet->PorPrecioGeneral;
                break;

            case 2:
                $Margen = $ListaCostoDet->PorPrecioEspecial;
                break;

            case 3:
                $Margen = $ListaCostoDet->PorPrecioLicitacion;
                break;
        }
        return $Margen;
    }

    public static function MesTexto($Mes) {
        $MesEnTexto = "";
        switch ($Mes) {
            case 1:
                $MesEnTexto = "Enero";
                break;
            case 2:
                $MesEnTexto = "Febrero";
                break;
            case 3:
                $MesEnTexto = "Marzo";
                break;
            case 4:
                $MesEnTexto = "Abril";
                break;
            case 5:
                $MesEnTexto = "Mayo";
                break;
            case 6:
                $MesEnTexto = "Junio";
                break;
            case 7:
                $MesEnTexto = "Julio";
                break;
            case 8:
                $MesEnTexto = "Agosto";
                break;
            case 9:
                $MesEnTexto = "Septiembre";
                break;
            case 10:
                $MesEnTexto = "Octubre";
                break;
            case 11:
                $MesEnTexto = "Noviembre";
                break;
            case 12:
                $MesEnTexto = "Diciembre";
                break;
        }
        return $MesEnTexto;
    }

    public static function DevMeses() {
        $Meses = array('' => 'Seleccione',
            '1' => 'Enero',
            '2' => 'Febrero',
            '3' => 'Marzo',
            '4' => 'Abril',
            '5' => 'Mayo',
            '6' => 'Junio',
            '7' => 'Julio',
            '8' => 'Agosto',
            '9' => 'Septiembre',
            '10' => 'Octubre',
            '11' => 'Noviembre',
            '12' => 'Diciembre');
        return $Meses;
    }

    public static function DevAnnios() {
        $i = 2009;
        $Annios["Seleccione"] = "Seleccione";
        while ($i < 2023) {
            $Annios[$i] = $i;
            $i++;
        }
        //$Annios = array('' => 'Seleccione', '2009' => '2009', '2010' => '2010', '2011' => '2011', '2012' => '2012', '2013' => '2013', '2014' => '2014', '2015' => '2015', '2016' => '2016', '2017' => '2017', '2018' => '2018', '2019' => '2019');
        return $Annios;
    }

    /**
     * Copia un archivo especificado en la ruta dada por parametro.
     * @param <string> Direccion del directorio donde se encuentra el archivo a copiar.
     * @param <string> Direccion del archivo donde se va a copiar.
     * @param <int>
     * @param <int>
     * */
    public static function CopiarArchivo($DirectorioOl, $DireccionFl, $IdDirectorio, $IdArchivo) {
        
    }

    /**
     * Funcion que retorna la direccion dentro del proyecto de los temas de ayuda
     * de un modulo especificado por parametro.
     * @param integer $intIdAyuda
     * @return la url de la ayuda requerida 
     * */
    public static function InvocarAyuda($intIdAyuda) {
        $temasAyuda = new TemasAyudaRecord();
        $temasAyuda = TemasAyudaRecord::finder()->FindByPk($intIdAyuda);

        if (count($temasAyuda) > 0) {
            return $temasAyuda;
        } else {
            return false;
        }
    }

    /**
     * @todo Reestructurar esta funcion
     * @param type $IdCambioPrecio 
     * */
    public static function ActualizarListaPrecios($IdCambioPrecio) {

        // Se busca el encabezado del cambio 
    }

    /**
     * Llena los Cbos de los formularios de NuevoTercero y EditarTercero con los registros de la BD
     * @param objeto $objVista el page desde el cual se invoca el metodo
     * @deprecated Este metodo debe pasarse a una clase diferente.
     * */
    public static function LlenarCbosTerceros($objVista) {
        // Select de ciudades.
        $sql = "SELECT * FROM ciudades ORDER BY NmCiudad";
        $objVista->Cbo_Ciudad->DataSource = CiudadesRecord::finder()->findAllBySql($sql);
        $objVista->Cbo_Ciudad->dataBind();

        $objVista->Cbo_ClasificacionTributaria->DataSource = ClasificacionesTributariasRecord::finder()->FindAll();
        $objVista->Cbo_ClasificacionTributaria->dataBind();

        // Select de asesores.
        $objVista->Cbo_Asesor->DataSource = AsesoresRecord::finder()->findAllBy_Inactivo('0');
        $objVista->Cbo_Asesor->dataBind();

        // Select de asesores.
        $objVista->Cbo_AsesorServicliente->DataSource = AsesoresRecord::finder()->findAllBy_Inactivo('0');
        $objVista->Cbo_AsesorServicliente->dataBind();

        // Select de forma pago.
        $FormasPago = FormasPagoRecord::finder()->findAll();
        $objVista->Cbo_Forma_Pago_Cli->DataSource = $FormasPago;
        $objVista->Cbo_Forma_Pago_Cli->dataBind();

        $objVista->Cbo_Forma_Pago_Cli->SelectedValue = 3;

        if (funciones::PermisosConsultas($objVista->User->Name, 47) == false)
            $objVista->Cbo_Forma_Pago_Cli->Enabled = false;

        $objVista->Cbo_Forma_Pago_Pro->DataSource = $FormasPago;
        $objVista->Cbo_Forma_Pago_Pro->dataBind();

        $objVista->Cbo_Forma_Pago_Pro->SelectedValue = 2;

        if (funciones::PermisosConsultas($objVista->User->Name, 48) == false)
            $objVista->Cbo_Forma_Pago_Pro->Enabled = false;

        // Condiciones de entrega.
        $objVista->Cbo_CondicionEntrega->DataSource = CondicionesEntregaRecord::finder()->findAll();
        $objVista->Cbo_CondicionEntrega->dataBind();

        // Select de forma pago.
        $objVista->Cbo_TpIdentificacion->DataSource = TipoIdentificacionRecord::finder()->findAll();
        $objVista->Cbo_TpIdentificacion->dataBind();

        // Select Lista de Costos.
        $sql = "Select IdListaCostosProv, concat(IdListaCostosProv, ' - ', NmListaCostos) as NmListaCostos from lista_costos_prov where Inactivo = 0";
        $objVista->Cbo_ListaCostos->DataSource = ListaCostosProvRecord::finder()->findAllBySql($sql);
        $objVista->Cbo_ListaCostos->dataBind();

        // Select Lista de Precios.
        $sql = "Select IdListaPrecios, concat(IdListaPrecios, ' - ', NmListaPrecios) as NmListaPreciosCod from lista_precios";
        $objVista->Cbo_ListaPrecios->DataSource = ListaPreciosRecord::finder('ListaPreciosExtRecord')->findAllBySql($sql);
        $objVista->Cbo_ListaPrecios->dataBind();

        // Select tipos de ordenes de compra
        $objVista->CboTpOrdenes->DataSource = TpOrdenCompraTerceroRecord::finder()->findAll();
        $objVista->CboTpOrdenes->dataBind();

        // Select tipos de clientes
        $objVista->CboTpCliente->DataSource = TiposClientesRecord::finder()->findAll();
        $objVista->CboTpCliente->dataBind();

        if (funciones::PermisosConsultas($objVista->User->Name, 144) == false) {
            $objVista->Cbo_ListaCostos->Enabled = false;
            $objVista->Cbo_ListaPrecios->Enabled = false;
        } else {
            $objVista->Cbo_ListaCostos->Enabled = true;
            $objVista->Cbo_ListaPrecios->Enabled = true;
        }
    }

    /**
     * Llena los Cbos de los formularios de NuevoItem y EditarIte con los registros de la BD
     * @param objeto $objVista el page desde el cual se invoca el metodo
     * @deprecated Este metodo debe pasarse a una clase diferente.
     * */
    public static function LlenarCbosItem($objVista) {
        $Unidades = new UnidadesMedidaRecord;
        $Unidades = UnidadesMedidaRecord::finder()->findAll('Inactivo = 0');

        // Cbo_UMM.
        $objVista->Cbo_UMM->DataSource = $Unidades;
        $objVista->Cbo_UMM->dataBind();

        // Traemos los registros de lineas.
        $objVista->Cbo_Linea->DataSource = LineasRecord::finder()->findAllBy_Autorizado(1);
        $objVista->Cbo_Linea->dataBind();

        // Traemos los registros de ubicaciones.
        $objVista->Cbo_Ubicacion->DataSource = UbicacionesRecord::finder()->findAll();
        $objVista->Cbo_Ubicacion->dataBind();

        // Cbo_CCostos.
        $objVista->Cbo_CCostos->DataSource = CentroCostosRecord::finder()->findAll();
        $objVista->Cbo_CCostos->dataBind();

        // Cbo_Departamentos
        $objVista->Cbo_Departamentos->DataSource = DepartamentosRecord::finder()->findAll();
        $objVista->Cbo_Departamentos->dataBind();
    }

    ////// Metodos compartidos de cotizaciones

    /**
     * Recupera los filtros personalizados de un usuario y restablece los controles segun 
     * esten configurados.
     * @param objeto $objVista La vista actual y sus propiedades
     * */
    public static function DatosTrabajo($objVista, $BoolVerificarChecks = false) {

        $DatosTrabajo = new DatosTrabajoRecord();
        $DatosTrabajo = DatosTrabajoRecord::finder()->FindByPk($objVista->User->Name);

        $Marcar = false;
        if (count($DatosTrabajo) > 0) {
            $objVista->CboFlProveedor->SelectedValue = $DatosTrabajo->Proveedor;
            if ($objVista->CboFlProveedor->SelectedValue != "") {
                $Marcar = true;
            }
            $objVista->CboFltMarcas->SelectedValue = $DatosTrabajo->IdMarcaCot;
            if ($objVista->CboFltMarcas->SelectedValue != '')
                $Marcar = true;

            $objVista->CboFltListas->SelectedValue = $DatosTrabajo->IdListaCostosCot;
            if ($objVista->CboFltListas->SelectedValue != '')
                $Marcar = true;

            $objVista->CboFltLineas->SelectedValue = $DatosTrabajo->IdLineaCot;
            if ($objVista->CboFltLineas->SelectedValue != '')
                $Marcar = true;

            $objVista->CboFltCategorias->SelectedValue = $DatosTrabajo->IdCategoriaPortafolio;
            if ($objVista->CboFltCategorias->SelectedValue != '') {
                $Marcar = true;
            }

            $objVista->ChkFltSoloEnlazados->checked = $DatosTrabajo->ListaCostosEnlazadaCot;
            if ($objVista->ChkFltSoloEnlazados->checked)
                $Marcar = true;

            $objVista->ChkFltSoloNoEnlazados->checked = $DatosTrabajo->ListaCostosNoEnlazadaCot;
            if ($objVista->ChkFltSoloNoEnlazados->checked)
                $Marcar = true;

            $objVista->ChkFltHabilitados->Checked = $DatosTrabajo->AprobadoCot;
            if ($objVista->ChkFltHabilitados->checked)
                $Marcar = true;

            $objVista->ChkFltNoHabilitados->Checked = $DatosTrabajo->NoAprobadoCot;
            if ($objVista->ChkFltNoHabilitados->checked)
                $Marcar = true;

            $objVista->ChkFltNullHabilitados->Checked = $DatosTrabajo->HabCotizarNull;
            if ($objVista->ChkFltNullHabilitados->checked)
                $Marcar = true;

            $objVista->ChkFltAceptadoCliente->Checked = $DatosTrabajo->AceptadoCliente;
            if ($objVista->ChkFltAceptadoCliente->checked)
                $Marcar = true;

            $objVista->ChkCantidades->Checked = $DatosTrabajo->CantidadMin;
            if ($objVista->ChkCantidades->checked)
                $Marcar = true;

            $objVista->ChkCostosProximos->Checked = $DatosTrabajo->CostosProximos;
            if ($objVista->ChkCostosProximos->checked)
                $Marcar = true;

            $objVista->ChkCostosGenerales->Checked = $DatosTrabajo->CostosGenerales;
            if ($objVista->ChkCostosGenerales->checked)
                $Marcar = true;

            $objVista->ChkCostosProyectados->Checked = $DatosTrabajo->CostosProyectados;
            if ($objVista->ChkCostosProyectados->checked)
                $Marcar = true;

            $objVista->ChkMargenEspecial->Checked = $DatosTrabajo->MargenEspecial;
            if ($objVista->ChkMargenEspecial->checked)
                $Marcar = true;

            $objVista->TxtComentarioInterno->Text = $DatosTrabajo->ComentarioInterno;
            if ($objVista->TxtComentarioInterno->Text != "")
                $Marcar = true;

            $objVista->TxtFiltroDescripcionCte->Text = $DatosTrabajo->DescripcionCte;
            if ($objVista->TxtFiltroDescripcionCte->Text != "")
                $Marcar = true;

            $objVista->ChkVendidos->Checked = $DatosTrabajo->Vendido;
            if ($objVista->ChkVendidos->checked)
                $Marcar = true;

            $objVista->ChkLCDInactivos->Checked = $DatosTrabajo->LCDInactivos;
            if ($objVista->ChkLCDInactivos->checked)
                $Marcar = true;

            $objVista->ChkAlternativa->Checked = $DatosTrabajo->Alternativa;
            if ($objVista->ChkAlternativa->checked)
                $Marcar = true;

            $objVista->ChkHabCotizar->Checked = $DatosTrabajo->HabCotizar;
            if ($objVista->ChkHabCotizar->checked)
                $Marcar = true;

            $objVista->ChkCerrados->Checked = $DatosTrabajo->Cerrados;
            if ($objVista->ChkCerrados->checked) {
                $Marcar = true;
            }

            $objVista->ChkContrato->Checked = $DatosTrabajo->EnContrato;
            if ($objVista->ChkContrato->checked) {
                $Marcar = true;
            }

            $objVista->ChkCostoEscala->Checked = $DatosTrabajo->CostoEscala;
            if ($objVista->ChkCostoEscala->checked) {
                $Marcar = true;
            }
            $objVista->ChkFltCotizacionesVigentes->Checked = $DatosTrabajo->ChkFltCotizacionesVigentes;
            if ($objVista->ChkFltCotizacionesVigentes->Checked) {
                $Marcar = true;
            }
            $objVista->Cbo_GruposPlantillas->SelectedValue = $DatosTrabajo->GruposPlantilla;
            if ($objVista->Cbo_GruposPlantillas->SelectedValue != '') {
                $Marcar = true;
                $objVista->Cbo_GruposPlantillas->BorderColor = 'red';
            }

            $objVista->ChkRevisados->Checked = $DatosTrabajo->Revisado;
            if ($objVista->ChkRevisados->Checked) {
                $Marcar = true;
                $objVista->ChkRevisados->BorderColor = 'red';
            }

            $objVista->ChkNoRevisados->Checked = $DatosTrabajo->NoRevisado;
            if ($objVista->ChkNoRevisados->Checked) {
                $Marcar = true;
                $objVista->ChkNoRevisados->BorderColor = 'red';
            }

            $objVista->ChkExportadosCP->Checked = $DatosTrabajo->ExportadoCP;
            if ($objVista->ChkExportadosCP->Checked) {
                $Marcar = true;
                $objVista->ChkExportadosCP->BorderColor = 'red';
            }

            $objVista->ChkNoExportadosCP->Checked = $DatosTrabajo->NoExportadoCP;
            if ($objVista->ChkNoExportadosCP->Checked) {
                $Marcar = true;
                $objVista->ChkNoExportadosCP->BorderColor = 'red';
            }

            if ($Marcar == true) {
                $objVista->IBtnFiltro->BorderStyle = "solid";
                $objVista->IBtnFiltro->BorderColor = "red";
                $objVista->IBtnFiltro->BorderWidth = 2;
                $objVista->IBtnFiltro->ToolTip = "La cotizacion contiene un filtro";
            } else {
                $objVista->IBtnFiltro->BorderWidth = 0;
            }

            //Cargando Checks de Visualizacion
            if ($BoolVerificarChecks) {
                $objVista->ChkIdCotizacion->Checked = $DatosTrabajo->ChkIdCotizacion;
                $objVista->ChkIdEQ->Checked = $DatosTrabajo->ChkIdEQ;
                $objVista->ChkCodCliente->Checked = $DatosTrabajo->ChkCodCliente;
                $objVista->ChkDescCliente->Checked = $DatosTrabajo->ChkDescCliente;
                $objVista->ChkMargenOriginal->Checked = $DatosTrabajo->ChkMargenOriginal;
                $objVista->ChkIdLista->Checked = $DatosTrabajo->ChkIdLista;
                $objVista->ChkCosto->Checked = $DatosTrabajo->ChkCosto;
                $objVista->ChkVigLista->Checked = $DatosTrabajo->ChkVigLista;
                $objVista->ChkVarCot->Checked = $DatosTrabajo->ChkVarCot;
                $objVista->ChkVarPrecio->Checked = $DatosTrabajo->ChkVarPrecio;
                $objVista->ChkVarUltimo->Checked = $DatosTrabajo->ChkVarUltimo;
                $objVista->ChkUMC->Checked = $DatosTrabajo->ChkUMC;
                $objVista->ChkCantMinCompra->Checked = $DatosTrabajo->ChkCantMinCompra;
                $objVista->ChkCostoEscala->Checked = $DatosTrabajo->CostoEscala;
                $objVista->ChkLinea->Checked = $DatosTrabajo->Linea;
                $objVista->ChkGrupo->Checked = $DatosTrabajo->Grupo;
                $objVista->ChkSubGrupo->Checked = $DatosTrabajo->SubGrupo;
            }
        }
    }

    /**
     * Carga los datagrid de CotizacionesItem y CotizacionesItemEditar
     * @param objeto $objVista La vista actual
     * */
    public static function CargarDatagridDetCot($objVista, $BoolVerificarChecks = false) {

        $sql = "SELECT  
                    cotizaciones_det.IdCotizacionDet,
                    cotizaciones_det.IdCotizacion,
                    cotizaciones_det.CodEQ,
                    cotizaciones_det.IdItemCotizacion,
                    cotizaciones_det.FhDesdeLista,
                    cotizaciones_det.FhHastaLista,
                    cotizaciones_det.FhDesdePrecioCot,
                    cotizaciones_det.FhHastaPrecioCot,
                    cotizaciones_det.DescripcionCotizacion,
                    cotizaciones_det.DescripcionCliente,
                    cotizaciones_det.UMVCot,
                    cotizaciones_det.FactorVCot,
                    cotizaciones_det.CantidadCotizacion,
                    cotizaciones_det.CostoCotizacion,
                    cotizaciones_det.PrecioCotizacion,
                    cotizaciones_det.PrecioTecho,
                    cotizaciones_det.MargenOriginal,
                    cotizaciones_det.Margen,
                    cotizaciones_det.PorIvaCotizacion,
                    cotizaciones_det.DctoCotizacion,
                    cotizaciones_det.Redondeo,
                    cotizaciones_det.IdListaCostosDetCot,
                    cotizaciones_det.IdProveedorCot,
                    cotizaciones_det.ComentarioCotDet,
                    cotizaciones_det.ComentarioInterno,
                    cotizaciones_det.CodCliente,
                    cotizaciones_det.SubTotal,
                    cotizaciones_det.TotalIva,
                    cotizaciones_det.TotalDcto,
                    cotizaciones_det.Total,
                    cotizaciones_det.Habilitado,
                    cotizaciones_det.AceptadoCliente,
                    cotizaciones_det.VendidoAnterioridad,
                    cotizaciones_det.ExportadoCP,
                    cotizaciones_det.CProximo,
                    cotizaciones_det.Consumo,
                    cotizaciones_det.FactorCliente,
                    cotizaciones_det.UMCliente,
                    cotizaciones_det.PresentacionCliente,
                    cotizaciones_det.Adjudicado,
                    cotizaciones_det.Alternativa,
                    cotizaciones_det.DescuentoFcieroCot,
                    cotizaciones_det.CProyectado,
                    cotizaciones_det.Cerrado,
                    cotizaciones_det.Opcion,
                    cotizaciones_det.CostoEscala,
                    cotizaciones_det.ItemCliente,
                    cotizaciones_det.MarcaSugerida,
                    cotizaciones_det.UmCompraCliente,
                    cotizaciones_det.FcCompraCliente,
                    cotizaciones_det.GrupoPlantilla,
                    cotizaciones_det.CantMinVentaCliente,
                    cotizaciones_det.Revisado,
                    
                    cotizaciones_det.IdEscalaDet,if(Revisado=1,'SI','NO') as Revisado,
                       (SELECT SUM(Disponible) as Disponible
                       FROM lotes LEFT JOIN bodegas ON bodegas.IdBodega = lotes.Bodega 
                       WHERE bodegas.SumaDisponible=1 
                       AND lotes.Id_Item=cotizaciones_det.IdItemCotizacion) as SoporteDisponible, 
                       item.Por_Iva, item.Id_Item, item.EnNovedad, if(item.Contrato = 1,'Si','No') as Contrato, lista_costos_prov_det.CostoUMM, lista_costos_prov_det.Presentacion, 
                       lista_costos_prov_det.RefFabricante, lista_costos_prov_det.CantMinimaVenta, lista_costos_prov_det.IdMarca, marcas.NmMarca, lista_costos_prov_det.FhHasta, lista_costos_prov_det.CategoriaPortafolio,
                       CONCAT(lista_costos_prov.NmListaCostos,': ',cotizaciones_det.FhDesdeLista,' - ',cotizaciones_det.FhHastaLista) as NmListaCostos,
                       CONCAT(cotizaciones_det.FhDesdePrecioCot,' - ',cotizaciones_det.FhHastaPrecioCot) as Soporte,
                       lista_costos_prov_det.HabCotizar, lista_costos_prov_det.IvaLC, (CostoCotizacion/(1-Margen/100)) as PrecioBruto, lista_costos_prov_det.UMC, lista_costos_prov_det.CantMinimaCompra,                                               
                       (((PrecioCotizacion-CostoCotizacion)/PrecioCotizacion)*100) as Utilidad, lista_costos_prov_det.Inactivo as LCDInactiva,lista_costos_prov_det.Eleccion,
                       (CASE cotizaciones_det.Habilitado WHEN 0 THEN 'NO' WHEN 1 THEN 'SI' WHEN null THEN '' END) AS Habilitado,
                       (CASE cotizaciones_det.Alternativa WHEN 0 THEN 'NO' WHEN 1 THEN 'SI' END) AS Alternativa,lista_costos_prov_det.IdListaCostosProvDet,terceros.NombreCorto as Proveedor,
                       tiposcompras.Alias as TipoCompra,(CantidadCotizacion * PrecioCotizacion) as SubTotalDet, PrecioTecho,lineas.NmLinea,grupos.NmGrupo,subgrupos.NmSubgrupo,FORMAT(sql_precio_cotizacion_item.Precio,2) as Precio,CONCAT(sql_precio_cotizacion_item.FhDesde,' a ',sql_precio_cotizacion_item.FhHasta) as VigenciaPrecio,
                       FORMAT(((cotizaciones_det.PrecioCotizacion - sql_precio_cotizacion_item.Precio) / sql_precio_cotizacion_item.Precio)* 100,2)  as  VariacionPrecio,FORMAT(((cotizaciones_det.PrecioCotizacion - movimientos_det.Precio) / movimientos_det.Precio) * 100,2) as VarUltimaVenta,sql_escalas_cotizaciones.IdEscalaDet
                ,kits_det.IdKit as IdKitCot,CCGenon.CostoUMM  as CostoGen,FORMAT(((CCGenon.CostoUMM - cotizaciones_det.CostoCotizacion) / CCGenon.CostoUMM ) * 100 ,2)as VarCostoGen
                ,lista_costos_prov_det.CodProveedor
                FROM cotizaciones_det 
                LEFT JOIN cotizaciones on cotizaciones.IdCotizacion = cotizaciones_det.IdCotizacion
                LEFT JOIN lista_costos_prov_det on cotizaciones_det.IdListaCostosDetCot=lista_costos_prov_det.IdListaCostosProvDet
                LEFT JOIN lista_costos_prov_det as ListaDet on ListaDet.IdListaCostosProvDet = lista_costos_prov_det.IdListaDetReferencia
                LEFT JOIN tiposcompras on tiposcompras.IdTipoCompra=lista_costos_prov_det.TipoCompra 
                LEFT JOIN marcas on lista_costos_prov_det.IdMarca=marcas.IdMarca 
                LEFT JOIN lista_costos_prov on lista_costos_prov.IdListaCostosProv = lista_costos_prov_det.IdListaCostosProv
                LEFT JOIN lista_costos_prov as ListaProv on ListaProv.IdListaCostosProv = ListaDet.IdListaCostosProv
                LEFT JOIN terceros on terceros.IdTercero = if(lista_costos_prov_det.IdListaDetReferencia is NULL ,lista_costos_prov.IdTercero,ListaProv.IdTercero)
                LEFT JOIN item on cotizaciones_det.IdItemCotizacion=item.Id_Item
                LEFT JOIN lista_costos_prov_det as CCGenon  on CCGenon.IdListaCostosProvDet = item.IdListaCostosDetItem
                LEFT JOIN lineas on lineas.IdLinea = lista_costos_prov_det.IdLineaLC
                LEFT JOIN grupos on grupos.IdLinea = lineas.IdLinea and grupos.IdGrupo = lista_costos_prov_det.IdGrupoLC
                LEFT JOIN subgrupos on subgrupos.IdGrupo = grupos.IdGrupo and subgrupos.IdLinea = lineas.IdLinea and subgrupos.IdSubgrupo = lista_costos_prov_det.IdSubGrupoLC
                LEFT JOIN sql_precio_cotizacion_item on sql_precio_cotizacion_item.Id_Item = item.Id_Item and sql_precio_cotizacion_item.IdDireccion = cotizaciones.IdDireccionCotizacion
                LEFT JOIN sql_ultima_fra_item_tercero_cot on sql_ultima_fra_item_tercero_cot.Id_Item = item.Id_Item and sql_ultima_fra_item_tercero_cot.IdTercero = cotizaciones.IdTerceroCotizacion 
                LEFT JOIN sql_escalas_cotizaciones on sql_escalas_cotizaciones.IdListacostosDet = if(lista_costos_prov_det.IdListaDetReferencia is not null,lista_costos_prov_det.IdListaDetReferencia,lista_costos_prov_det.IdListaCostosProvDet) and sql_escalas_cotizaciones.FhHasta >= CURDATE()
                LEFT JOIN movimientos_det on movimientos_det.NroDocumento = sql_ultima_fra_item_tercero_cot.NroDocumento and movimientos_det.Id_Item = sql_ultima_fra_item_tercero_cot.Id_Item and movimientos_det.FechaDet >= DATE_SUB(CURDATE(),INTERVAL 1 YEAR)
                LEFT JOIN kits_det on kits_det.IdKitDet = cotizaciones_det.IdKitCot
                WHERE cotizaciones.IdCotizacion=" . $objVista->Request['IdCot'];


        if ($objVista->CboFlProveedor->SelectedValue != "") {
            $sql = $sql . " AND terceros.IdTercero = " . $objVista->CboFlProveedor->SelectedValue;
        }

        if ($objVista->CboFltMarcas->SelectedValue != "")
            $sql = $sql . " AND  lista_costos_prov_det.IdMarca=" . $objVista->CboFltMarcas->SelectedValue;

        if ($objVista->CboFltListas->SelectedValue != "")
            $sql = $sql . " AND  lista_costos_prov_det.IdListaCostosProv=" . $objVista->CboFltListas->SelectedValue;

        if ($objVista->CboFltLineas->SelectedValue != "")
            $sql = $sql . " AND lista_costos_prov_det.IdLineaLC=" . $objVista->CboFltLineas->SelectedValue;

        if ($objVista->Cbo_Grupo->SelectedValue != "")
            $sql = $sql . " AND lista_costos_prov_det.IdGrupoLC =" . $objVista->Cbo_Grupo->SelectedValue;

        if ($objVista->Cbo_Subgrupo->SelectedValue != "")
            $sql = $sql . " AND  lista_costos_prov_det.IdSubGrupoLC =" . $objVista->Cbo_Subgrupo->SelectedValue;

        if ($objVista->CboFltCategorias->SelectedValue != "") {
            $sql = $sql . " AND lista_costos_prov_det.CategoriaPortafolio='" . $objVista->CboFltCategorias->SelectedValue . "'";
        }

        if ($objVista->ChkMargenEspecial->checked == true) {
            $sql = $sql . " AND  lista_costos_prov_det.MargenEspecial = 1";
        }

        if ($objVista->ChkFltSoloEnlazados->checked == true)
            $sql = $sql . " AND  cotizaciones_det.IdListaCostosDetCot is not NULL";

        if ($objVista->ChkFltSoloNoEnlazados->checked == true)
            $sql = $sql . " AND  cotizaciones_det.IdListaCostosDetCot is NULL";

        if ($objVista->ChkFltHabilitados->Checked == true)
            $sql = $sql . " AND  cotizaciones_det.Habilitado=1";

        if ($objVista->ChkFltAceptadoCliente->Checked == true)
            $sql = $sql . " AND  cotizaciones_det.AceptadoCliente = 1";

        if ($objVista->ChkFltNoHabilitados->Checked == true)
            $sql = $sql . " AND  cotizaciones_det.Habilitado=0";

        if ($objVista->ChkFltNullHabilitados->Checked == true)
            $sql = $sql . " AND  cotizaciones_det.Habilitado is null";

        if ($objVista->ChkCantidades->Checked == true)
            $sql = $sql . " AND  CantidadCotizacion < lista_costos_prov_det.CantMinimaVenta";

        if ($objVista->ChkVendidos->Checked == true)
            $sql = $sql . " AND  VendidoAnterioridad = 1";

        if ($objVista->ChkNetosCeros->Checked == true)
            $sql = $sql . " AND  CostoCotizacion = 0";

        if ($objVista->ChkFhVencidas->Checked == true)
            $sql = $sql . " AND  (FhHastaLista < '" . date('Y-m-d') . "' OR FhHastaLista < FhHastaPrecioCot)";

        if ($objVista->TxtIdItem->Text != '')
            $sql = $sql . " AND  IdItemCotizacion = " . $objVista->TxtIdItem->Text;
        else {
            $objVista->TxtIdItem->Text = "";
            $objVista->TxtDescripcion->Text = "";
        }

        if ($objVista->ChkRevisados->Checked == true) {
            $sql = $sql . " AND  Revisado = 1";
        }

        if ($objVista->ChkNoRevisados->Checked == true) {
            $sql = $sql . " AND  (Revisado = 0 or Revisado is null )";
        }

        if ($objVista->ChkExportadosCP->Checked == true) {
            $sql = $sql . " AND  ExportadoCP = 1";
        }

        if ($objVista->ChkNoExportadosCP->Checked == true) {
            $sql = $sql . " AND  ExportadoCP = 0";
        }

        if ($objVista->ChkCostosProximos->Checked == true)
            $sql = $sql . " AND  CProximo = 1";

        if ($objVista->ChkCostosGenerales->Checked == true)
            $sql = $sql . " AND  CProximo = 0 AND CProyectado = 0";

        if ($objVista->ChkCostosProyectados->Checked == true)
            $sql = $sql . " AND  CProximo = 1 AND CProyectado = 1";

        if ($objVista->TxtComentarioInterno->Text != '')
            $sql = $sql . " AND  ComentarioInterno LIKE '%" . $objVista->TxtComentarioInterno->Text . "%'";

        if ($objVista->TxtFiltroDescripcion->Text != '')
            $sql = $sql . " AND  DescripcionCotizacion LIKE '%" . $objVista->TxtFiltroDescripcion->Text . "%'";

        if ($objVista->TxtFiltroDescripcionCte->Text != '')
            $sql = $sql . " AND  DescripcionCliente LIKE '%" . $objVista->TxtFiltroDescripcionCte->Text . "%'";

        if ($objVista->ChkMargenCero->Checked == true)
            $sql = $sql . " AND  Margen = 0";

        if ($objVista->ChkLCDInactivos->Checked == true)
            $sql = $sql . " AND  lista_costos_prov_det.Inactivo = 1";

        if ($objVista->ChkAlternativa->Checked == true)
            $sql = $sql . " AND  Alternativa = 1";

        if ($objVista->ChkHabCotizar->Checked == true) {
            $sql = $sql . " AND  lista_costos_prov_det.HabCotizar = 1";
        }

        if ($objVista->ChkNoHabCotizar->Checked == true) {
            $sql = $sql . " AND  lista_costos_prov_det.HabCotizar = 0";
        }

        if ($objVista->ChkHabCotizarNull->Checked == true) {
            $sql = $sql . " AND  lista_costos_prov_det.HabCotizar is null";
        }

        if ($objVista->ChkConDisponible->Checked == true) {
            $sql = $sql . " AND (SELECT SUM(Disponible) as Disponible FROM lotes LEFT JOIN bodegas ON bodegas.IdBodega = lotes.Bodega 
                            WHERE bodegas.SumaDisponible=1 AND lotes.Id_Item=cotizaciones_det.IdItemCotizacion) > 0";
        }

        if ($objVista->ChkCerrados->Checked == true) {
            $sql = $sql . " AND  Cerrado = 1";
        }

        if ($objVista->ChkContrato->Checked == true) {
            $sql = $sql . " AND  item.Contrato = 1";
        }
        if ($objVista->Cbo_OpcionFiltro->SelectedValue != '') {
            if ($objVista->Cbo_OpcionFiltro->SelectedValue > 0) {
                $sql = $sql . " AND  Opcion = " . $objVista->Cbo_OpcionFiltro->SelectedValue;
            } else {
                $sql = $sql . " AND  Opcion is null ";
            }
        }

        if ($objVista->ChkCostoEscala->Checked == true) {
            $sql = $sql . " AND  cotizaciones_det.CostoEscala = 1";
        }

        if ($objVista->Cbo_GruposPlantillas->SelectedValue != '') {
            $sql = $sql . " AND GrupoPlantilla like '%" . $objVista->Cbo_GruposPlantillas->SelectedValue . "%'";
        }

        // Muestra los productos
        if (isset($objVista->ChkTodos)) {
            if (!$objVista->ChkTodos->Checked) {

                if ($objVista->ChkNoAsignados->Checked)
                    $sql = $sql . " AND IdItemCotizacion IS NULL";

                if ($objVista->ChkAsignados->Checked)
                    $sql = $sql . " AND IdItemCotizacion > 1";
            }
        }

        if ($objVista->ChkFltCotizacionesVigentes->Checked == true) {
            $sql = $sql . "  and cotizaciones_det.IdItemCotizacion in
                           ( select  cot_det.IdItemCotizacion from cotizaciones_det  as cot_det  
                           LEFT JOIN cotizaciones as cot on cot.IdCotizacion = cot_det.IdCotizacion 
                           where cot_det.IdCotizacion != cotizaciones.IdCotizacion and cot_det.IdItemCotizacion = cotizaciones_det.IdItemCotizacion and  cot.IdTerceroCotizacion = cotizaciones.IdTerceroCotizacion and cot.IdDireccionCotizacion = cotizaciones.IdDireccionCotizacion and cot_det.FhHastaLista >= CURDATE())";
        }
        $sql = $sql . " GROUP BY cotizaciones_det.IdCotizacionDet ORDER BY IdKitCot,GrupoPlantilla, DescripcionCotizacion ";
        $Datos = CotizacionesDetRecord::finder('CotizacionesDetExtRecord')->with_Item()->with_Tercero()->FindAllBySql($sql);
        $objVista->DGCotizacionesDet->DataSource = $Datos;
        $objVista->DGCotizacionesDet->dataBind();
//        echo $sql;
//        return false;
        // Para guardar la consulta hecha en el datagrid y utilizar este dato en la consulta del Pop-up, EditarDetalleCotizacion.
        //if(isset($objVista->TASql)) {
        $SqlEditDet = str_replace("%", '(-)', $sql); //Aqui cambiamos los % por (-) ya que el % pegado de palabras forma caracteres especiales y genera error en la consulta.
        $objVista->TASql->Data = $SqlEditDet;
        $objVista->TASqlEdit->Data = $SqlEditDet;
        $objVista->setViewState('DatosCotizacion', $Datos);
        //}

        $objVista->LblNroRegistros->Text = $objVista->DGCotizacionesDet->ItemCount;

        if ($BoolVerificarChecks) {
            //Realizando la activacion y desactivacion de las columnas con loc checks
            if ($objVista->ChkIdCotizacion->Checked == true) {
                $objVista->ClmIdCotizacionDet->Visible = true;
            } else {
                $objVista->ClmIdCotizacionDet->Visible = false;
            }

            if ($objVista->ChkIdEQ->Checked == true) {
                $objVista->ClmCodEQ->Visible = true;
            } else {
                $objVista->ClmCodEQ->Visible = false;
            }

            if ($objVista->ChkCodCliente->Checked == true) {
                $objVista->ClmCodCliente->Visible = true;
            } else {
                $objVista->ClmCodCliente->Visible = false;
            }

            if ($objVista->ChkDescCliente->Checked == true) {
                $objVista->ClmDescripcionCliente->Visible = true;
            } else {
                $objVista->ClmDescripcionCliente->Visible = false;
            }

            if ($objVista->ChkMargenOriginal->Checked == true) {
                $objVista->ClmMargenOriginal->Visible = true;
            } else {
                $objVista->ClmMargenOriginal->Visible = false;
            }

            if ($objVista->ChkIdLista->Checked == true) {
                $objVista->ClmListaCosto->Visible = true;
            } else {
                $objVista->ClmListaCosto->Visible = false;
            }

            if ($objVista->ChkCosto->Checked == true) {
                $objVista->ClmCosto->Visible = true;
            } else {
                $objVista->ClmCosto->Visible = false;
            }

            if ($objVista->ChkVigLista->Checked == true) {
                $objVista->ClmVigenciaListaPrecios->Visible = true;
            } else {
                $objVista->ClmVigenciaListaPrecios->Visible = false;
            }

            if ($objVista->ChkVarCot->Checked == true) {
                $objVista->ClmVarUltimaCot->Visible = true;
            } else {
                $objVista->ClmVarUltimaCot->Visible = false;
            }

            if ($objVista->ChkVarPrecio->Checked == true) {
                $objVista->ClmVarPrecioVigente->Visible = true;
            } else {
                $objVista->ClmVarPrecioVigente->Visible = false;
            }

            if ($objVista->ChkVarUltimo->Checked == true) {
                $objVista->ClmVarUltimoPrecio->Visible = true;
            } else {
                $objVista->ClmVarUltimoPrecio->Visible = false;
            }

            if ($objVista->ChkUMC->Checked == true) {
                $objVista->ClmUMC->Visible = true;
            } else {
                $objVista->ClmUMC->Visible = false;
            }

            if ($objVista->ChkCantMinCompra->Checked == true) {
                $objVista->ClmCantMinCompra->Visible = true;
            } else {
                $objVista->ClmCantMinCompra->Visible = false;
            }

            if ($objVista->ChkLinea->Checked == true) {
                $objVista->ClmLinea->Visible = true;
            } else {
                $objVista->ClmLinea->Visible = false;
            }

            if ($objVista->ChkGrupo->Checked == true) {
                $objVista->ClmGrupo->Visible = true;
            } else {
                $objVista->ClmGrupo->Visible = false;
            }

            if ($objVista->ChkSubGrupo->Checked == true) {
                $objVista->ClmIdSubGrupo->Visible = true;
            } else {
                $objVista->ClmIdSubGrupo->Visible = false;
            }
        }
    }

    /**
     * Carga los datagrid de CotizacionesItem y CotizacionesItemEditar
     * @param objeto $objVista La vista actual
     * */
    public static function CargarDatagridDetSolCot($objVista) {

        $sql = "SELECT cotizaciones_solicitudes_det.*,
                       proveedor.NombreCorto as Proveedor, marcas.NmMarca, lista_costos_prov_det.RefFabricante,
                       (CASE cotizaciones_solicitudes_det.Alternativa WHEN 0 THEN 'NO' WHEN 1 THEN 'SI' END) AS Alternativa,
                       lista_costos_prov_det.CategoriaPortafolio, lista_costos_prov_det.Presentacion,
                       CONCAT(lista_costos_prov.NmListaCostos,': ',cotizaciones_solicitudes_det.FhDesdeLista,' - ',cotizaciones_solicitudes_det.FhHastaLista) as NmListaCostos,
                       CONCAT(cotizaciones_solicitudes_det.FhDesdePrecioCot,' - ',cotizaciones_solicitudes_det.FhHastaPrecioCot) as Soporte,
                       (((PrecioCotizacion-CostoCotizacion)/PrecioCotizacion)*100) as Utilidad,
                       lista_costos_prov_det.CantMinimaCompra, lista_costos_prov_det.CantMinimaVenta,
                       (SELECT SUM(Disponible) as Disponible FROM lotes LEFT JOIN bodegas ON bodegas.IdBodega = lotes.Bodega WHERE bodegas.SumaDisponible=1 AND lotes.Id_Item=cotizaciones_solicitudes_det.IdItemCotizacion) as SoporteDisponible, 
                       lista_costos_prov_det.IvaLC, lista_costos_prov_det.UMC, lista_costos_prov_det.Inactivo as LCDInactiva
                 FROM cotizaciones_solicitudes_det 
                LEFT JOIN terceros as proveedor ON proveedor.IdTercero = cotizaciones_solicitudes_det.IdProveedorCot
                LEFT JOIN lista_costos_prov_det on cotizaciones_solicitudes_det.IdListaCostosDetCot = lista_costos_prov_det.IdListaCostosProvDet 
                LEFT JOIN marcas on lista_costos_prov_det.IdMarca = marcas.IdMarca 
                LEFT JOIN lista_costos_prov on lista_costos_prov_det.IdListaCostosProv = lista_costos_prov.IdListaCostosProv
                LEFT JOIN item on cotizaciones_solicitudes_det.IdItemCotizacion=item.Id_Item
                WHERE cotizaciones_solicitudes_det.IdSolicitud=" . $objVista->Request['IdSolicitud'];

        if (isset($objVista->ChkHabilitadoLista)) {
            if ($objVista->ChkHabilitadoLista->Checked)
                $sql = $sql . " AND lista_costos_prov_det.HabCotizar > 1";
        }

        if ($objVista->CboFltMarcas->SelectedValue != "")
            $sql = $sql . " AND  lista_costos_prov_det.IdMarca=" . $objVista->CboFltMarcas->SelectedValue;

        if ($objVista->CboFltListas->SelectedValue != "")
            $sql = $sql . " AND  lista_costos_prov_det.IdListaCostosProv=" . $objVista->CboFltListas->SelectedValue;

        if ($objVista->CboFltLineas->SelectedValue != "")
            $sql = $sql . " AND item.IdLinea=" . $this->CboFltLineas->SelectedValue;

        if ($objVista->CboFltCategorias->SelectedValue != "") {
            $sql = $sql . " AND lista_costos_prov_det.CategoriaPortafolio='" . $objVista->CboFltCategorias->SelectedValue . "'";
        }

        if ($objVista->ChkMargenEspecial->checked == true)
            $sql = $sql . " AND  lista_costos_prov_det.MargenEspecial = 1";

        if ($objVista->ChkFltSoloEnlazados->checked == true)
            $sql = $sql . " AND  cotizaciones_solicitudes_det.IdListaCostosDetCot is not NULL";

        if ($objVista->ChkFltSoloNoEnlazados->checked == true)
            $sql = $sql . " AND  cotizaciones_solicitudes_det.IdListaCostosDetCot is NULL";

        if ($objVista->ChkFltHabilitados->Checked == true)
            $sql = $sql . " AND  cotizaciones_solicitudes_det.Habilitado=1";

        if ($objVista->ChkFltAceptadoCliente->Checked == true)
            $sql = $sql . " AND  cotizaciones_solicitudes_det.AceptadoCliente = 1";

        if ($objVista->ChkFltNoHabilitados->Checked == true)
            $sql = $sql . " AND  cotizaciones_solicitudes_det.Habilitado=0";

        if ($objVista->ChkCantidades->Checked == true)
            $sql = $sql . " AND  CantidadCotizacion < CantMinimaVenta";

        if ($objVista->ChkVendidos->Checked == true)
            $sql = $sql . " AND  VendidoAnterioridad = 1";

        if ($objVista->ChkNetosCeros->Checked == true)
            $sql = $sql . " AND  CostoCotizacion = 0";

        if ($objVista->ChkFhVencidas->Checked == true)
            $sql = $sql . " AND  (FhHastaLista < '" . date('Y-m-d') . "' OR FhHastaLista < FhHastaPrecioCot)";

        if ($objVista->TxtIdItem->Text != '')
            $sql = $sql . " AND  IdItemCotizacion = " . $objVista->TxtIdItem->Text;
        else {
            $objVista->TxtIdItem->Text = "";
            $objVista->TxtDescripcion->Text = "";
        }

        if ($objVista->ChkCostosProximos->Checked == true)
            $sql = $sql . " AND  CProximo = 1";

        if ($objVista->ChkCostosGenerales->Checked == true)
            $sql = $sql . " AND  CProximo = 0";

        if ($objVista->TxtComentarioInterno->Text != '')
            $sql = $sql . " AND  ComentarioInterno LIKE '%" . $objVista->TxtComentarioInterno->Text . "%'";

        if ($objVista->TxtFiltroDescripcion->Text != '')
            $sql = $sql . " AND  DescripcionCotizacion LIKE '%" . $objVista->TxtFiltroDescripcion->Text . "%'";

        if ($objVista->TxtFiltroDescripcionCte->Text != '')
            $sql = $sql . " AND  DescripcionCliente LIKE '%" . $objVista->TxtFiltroDescripcionCte->Text . "%'";

        if ($objVista->ChkMargenCero->Checked == true)
            $sql = $sql . " AND  Margen = 0";

        if ($objVista->ChkLCDInactivos->Checked == true)
            $sql = $sql . " AND  lista_costos_prov_det.Inactivo = 1";

        if ($objVista->ChkAlternativa->Checked == true)
            $sql = $sql . " AND  Alternativa = 1";

        if ($objVista->ChkHabCotizar->Checked == true)
            $sql = $sql . " AND  HabCotizar = 1";

        if ($objVista->ChkNoHabCotizar->Checked == true)
            $sql = $sql . " AND  HabCotizar = 0";

        if ($objVista->ChkConDisponible->Checked == true) {
            $sql = $sql . " AND (SELECT SUM(Disponible) as Disponible FROM lotes LEFT JOIN bodegas ON bodegas.IdBodega = lotes.Bodega 
                            WHERE bodegas.SumaDisponible=1 AND lotes.Id_Item=cotizaciones_det.IdItemCotizacion) > 0";
        }

        // Muestra los productos
        if (isset($objVista->ChkTodos)) {
            if (!$objVista->ChkTodos->Checked) {

                if ($objVista->ChkNoAsignados->Checked)
                    $sql = $sql . " AND IdItemCotizacion IS NULL";

                if ($objVista->ChkAsignados->Checked)
                    $sql = $sql . " AND IdItemCotizacion > 1";
            }
        }

        $sql = $sql . " ORDER BY CodEQ, ComentarioInterno ASC, DescripcionCotizacion, DescripcionCliente";
        $Datos = CotizacionesSolicitudesDetRecord::finder('CotizacionesSolicitudesDetExtRecord')->findAllBySql($sql);
        $objVista->DGCotizacionesDet->DataSource = $Datos;
        $objVista->DGCotizacionesDet->dataBind();


        $objVista->LblNroRegistros->Text = $objVista->DGCotizacionesDet->ItemCount;

        // Para guardar la consulta hecha en el datagrid y utilizar este dato en la consulta del Pop-up, EditarDetalleCotizacion.
        $objVista->TASql->Data = $sql;

        $objVista->setViewState('DatosCotizacion', $Datos);
    }

    /**
     * Pobla el "Cbo_Grupo" con los grupos pertenecientes a la linea seleccionada,
     * Este metodo responde al "SelectChanged" del TDropDrownList "Cbo_Linea"
     * @param el id de la linea seleccionada.
     * */
    public static function cambioLinea($sender, $param, $objVista) {
        if ($objVista->Cbo_Linea->SelectedValue != "") {
            $sql = "SELECT * FROM grupos WHERE IdLinea =" . $objVista->Cbo_Linea->SelectedValue . " and Autorizado =1";
            $grupos = GruposRecord::finder()->findAllBySql($sql);

            if (count($grupos) == 0) {
                $objVista->Nuevogrupo->Visible = true;
                $objVista->Cbo_Grupo->Enabled = false;
                $objVista->Cbo_Subgrupo->Enabled = false;
            } else {
                $objVista->Cbo_Grupo->Enabled = true;
                $objVista->Nuevogrupo->Visible = false;
            }

            $objVista->Cbo_Grupo->DataSource = $grupos;
            $objVista->Cbo_Grupo->dataBind();

            // En cada cambio de linea ponemos en blanco todo lo referido a subgrupo.
            $objVista->Cbo_Subgrupo->DataSource = "";
            $objVista->Cbo_Subgrupo->dataBind();

            $objVista->NuevoSubgrupo->Visible = false;
        } else {
            // de lo contrario ponemos en blanco todo lo referido a grupo y subgrupo.
            $objVista->Cbo_Grupo->DataSource = "";
            $objVista->Cbo_Grupo->dataBind();

            $objVista->Cbo_Subgrupo->DataSource = "";
            $objVista->Cbo_Subgrupo->dataBind();
        }
    }

    /**
     * Pobla el "Cbo_Subgrupo" con los subgrupos pertenecientes al grupo seleccionado,
     * Este metodo responde al "SelectedChanged" del TDropDrownList "Cbo_Grupo"
     * @param el id del grupo seleccionado.
     * */
    public static function cambioGrupo($sender, $param, $objVista) {
        if ($objVista->Cbo_Grupo->SelectedValue != "") {
            $sql = "SELECT * FROM subgrupos WHERE IdGrupo=" . $objVista->Cbo_Grupo->SelectedValue . " AND IdLinea=" . $objVista->Cbo_Linea->SelectedValue . " and Autorizado = 1";
            $subgrupo = SubGruposRecord::finder()->findAllBySql($sql);

            if (count($subgrupo) == 0) {
                $objVista->Cbo_Subgrupo->Enabled = false;
                $objVista->NuevoSubgrupo->Visible = true;
            } else {
                $objVista->Cbo_Subgrupo->Enabled = true;
                $objVista->NuevoSubgrupo->Visible = false;
            }

            $objVista->Cbo_Subgrupo->DataSource = $subgrupo;
            $objVista->Cbo_Subgrupo->dataBind();
        } else {
            $objVista->Cbo_Subgrupo->DataSource = "";
            $objVista->Cbo_Subgrupo->dataBind();
        }
    }

    /**
     * Valida si ya se hizo un pedido a una direccion de envio especifica con
     * el mismot tipo de orden y el mismo numero que intentan almacenar
     * */
    public static function ValidarTpOrdenPedido($objVista) {
        // Si el tipo de orden es ORDEN DE COMPRA o CONTRATO.
        if ($objVista->CboTiposOc->SelectedValue == 2 || $objVista->CboTiposOc->SelectedValue == 3)
            $MovPedSoporte = MovimientosRecord::finder()->FindBy_IdTpOc_AND_Soporte_AND_IdTercero_AND_IdDireccion($objVista->CboTiposOc->SelectedValue, $objVista->TxtSoporte->Text, $objVista->TxtIdTercero->Text, $objVista->Cbo_Direccion->SelectedValue);
        else {
            $sql = "SELECT Fecha, IdTercero, Soporte FROM movimientos WHERE Fecha >= '" . date('Y-m-d 00:00:00') . " AND Fecha<=" . date('Y-m-d 11:59:59') . "' AND IdTercero=" . Trim($objVista->TxtIdTercero->Text) . " AND Soporte = '" . Trim($objVista->TxtSoporte->Text) . "'";
            $MovPedSoporte = MovimientosRecord::finder()->FindAllBySql($sql);
        }

        return $MovPedSoporte;
    }

    /**
     * Valida si el item que se esta intentando crear ya esta creado en la base de datos.
     * @param $IdItem El item aba a validar
     * @param $objVista La vista Actual.
     * */
    public static function ValidarItem($IdItem, $objVista) {
        $Value = $IdItem;

        $itemRecord = new ItemRecord();
        $itemRecord = ItemRecord::finder()->findByPk($Value);

        if (count($itemRecord) != 0) {
            funciones::Mensaje("El item ya se encuentra creado.", 2, $this);
            $objVista->TxtItem->BorderColor = "red";
            $objVista->TxtItem->focus();
            return false;
        } else {
            $objVista->FraMensaje->Visible = false;
            $objVista->TxtItem->BorderColor = "";
            $objVista->TxtDescripcion->focus();
        }
    }

    /**
     * Calcula el costo UMMProximo de un producto se g
     * @param integer $Costo Costo de un producto
     * @param integer $Dcto Descuento del producto en la lista de costos
     * @param integer $FactorCompra el factor de compra
     * @return integer
     * */
    public static function CalcularCostoUMMProximo($intCosto, $intDcto, $intFactorCompra) {
        $intDcto = ($intCosto * $intDcto) / 100;
        $intCostoUMM = ($intCosto - $intDcto) / $intFactorCompra;

        return number_format($intCostoUMM, '2', '.', '');
    }

    /**
     * Define el texto que llevara el documento en el encabezado, segun el cliente al que
     * pertenece.
     * @param objeto $objVista La vista actual
     * */
    public static function Comentario($objVista) {
        if (($objVista->CboConcepto->SelectedValue == 55 || $objVista->CboConcepto->SelectedValue == 56 || $objVista->CboConcepto->SelectedValue == 36) && $objVista->Request['IdDoc'] != 48) {
            if (($objVista->TxtIdTercero->Text == '811032059') || ($objVista->TxtIdTercero->Text == '830039670'))
                $Texto = "APOYO TECNOLOGICO";
            else
                $Texto = "COMODATO";


            $objVista->TxtComentarios->Text = "Le recordamos que durante el tiempo que los equipos se encuentren en su poder estaran bajo su responsabilidad y cuidado y deben ser reintegrados a ABA Cientifica una vez termine el $Texto.";
        } else
            $objVista->TxtComentarios->Text = "";
    }

    /**
     * Valida si los invimas de un movimiento estan habiles, devuelve true si los invimas estan bien
     * o false si tiene algun invima vencido
     * 
     * @param integer $intIdMovimiento Movimiento que contiene los items a analizar
     * @param Object $objVista la vista para sacar los mensajes
     * */
    public static function ValidarInvimas($intIdMovimiento, $objVista, $OpReturn = '', $intIDMovDet = '') {
        $boolValidacion = true;
        $strSql = "SELECT movimientos_det.IdMovimientoDet, item.Descripcion, movimientos_det.Id_Item,movimientos_det.Estado
                    FROM movimientos_det
                    LEFT JOIN item ON movimientos_det.Id_Item = item.Id_Item
                    LEFT JOIN lista_costos_prov_det ON item.IdListaCostosDetItem = lista_costos_prov_det.IdListaCostosProvDet
                    WHERE IdMovimiento = " . $intIdMovimiento . " ";
        if ($intIDMovDet > 0) {
            $strSql .= " and movimientos_det.IdMovimientoDet = " . $intIDMovDet;
        }

        $strSql .= " AND (lista_costos_prov_det.FhVenceInvima < '" . date('Y-m-d') . "' "
                . "OR (lista_costos_prov_det.RegInvima = '' AND RegInvimaCompuesto='')"
                . "OR (lista_costos_prov_det.RegInvima IS NULL  AND RegInvimaCompuesto IS NULL )) ";

        $strMensaje = "";

        $arMovimientosDet = new MovimientosDetRecord();
        $arMovimientosDet = MovimientosDetRecord::finder('MovimientosDetExtRecord')->FindAllBySql($strSql);

        if (count($arMovimientosDet) > 0) {

            foreach ($arMovimientosDet as $arMovimientosDet) {
                if (funciones::ValidarCertificadoItem($arMovimientosDet->Id_Item) == false) {
                    $boolValidacion = false;
                    if ($OpReturn == 2 && $arMovimientosDet->Estado != 'AUTORIZADO') {
                        $strMensaje .= "El Item " . $arMovimientosDet->Id_Item . " " . $arMovimientosDet->Descripcion . " tiene el invima vencido o no tiene, solicita una segunda autorización.<br />";
                    } else if ($OpReturn != 2) {
                        $strMensaje .= "El Item " . $arMovimientosDet->Id_Item . " " . $arMovimientosDet->Descripcion . " tiene el invima vencido o no tiene, solicita una segunda autorización.<br />";
                    }
                }
            }

            if ($strMensaje != "") {
                funciones::Mensaje($strMensaje, 2, $objVista);
            }
        }
        if ($OpReturn == 2) {
            return $strMensaje;
        } else {
            return $boolValidacion;
        }
    }

    /**
     * Desc: se crea validacion para los productos que tengan certificado de renovacion o agotamiento de etiquetas vigente.
     * @param type $IdItem
     * @return boolean
     * Fh: 13/04/2018
     */
    public static function ValidarCertificadoItem($IdItem) {
        $Item = new ItemRecord();
        $Item = ItemRecord::finder()->FindByPk($IdItem);
        $Val = false;
        if (count($Item) > 0 && $Item->IdListaCostosDetItem > 0) {
            $ListaDet = new ListaCostosProvDetRecord();
            $ListaDet = ListaCostosProvDetRecord::finder()->FindByPk($Item->IdListaCostosDetItem);
            if (count($ListaDet) > 0) {
                if ($ListaDet->CertAgotamientoEtiquetas != '' && $ListaDet->FhVenCert >= date("Y-m-d")) {
                    $Val = true;
                } else if ($ListaDet->NroRadicadoInvima != '' && !empty($ListaDet->NroRadicadoInvima)) {
                    $Val = true;
                } else {
                    return $Val;
                }
            } else {
                return $Val;
            }
        } else {
            return $Val;
        }
        return $Val;
    }

    /**
     * Valida la vida util de los productos
     * 
     * @param integer $intIdMovimiento Movimiento que contiene los items a analizar
     * @param Object $objVista la vista para sacar los mensajes
     * */
    public static function ValidarVidaUtil($intIdMovimiento, $objVista, $OpReturn = '', $intMovDet = 0) {
        $boolValidacion = true;

        $strSql = "SELECT movimientos_det.IdMovimientoDet, item.Descripcion, movimientos_det.Id_Item, timestampdiff(month,curdate(),FhVencimiento) as Cantidad, lista_costos_prov_det.VidaUtilMinima as CantOperada,movimientos_det.Estado
                    FROM movimientos_det
                    LEFT JOIN item ON movimientos_det.Id_Item = item.Id_Item
                    LEFT JOIN lista_costos_prov_det ON item.IdListaCostosDetItem = lista_costos_prov_det.IdListaCostosProvDet
                    WHERE IdMovimiento = " . $intIdMovimiento . "";
        if ($intMovDet > 0) {
            $strSql .= " and movimientos_det.IdMovimientoDet = " . $intMovDet;
        }
        $strSql .= " AND timestampdiff(month,curdate(),FhVencimiento) < lista_costos_prov_det.VidaUtilMinima";

        $strMensaje = "";

        $arMovimientosDet = new MovimientosDetRecord();
        $arMovimientosDet = MovimientosDetRecord::finder('MovimientosDetExtRecord')->FindAllBySql($strSql);

        if (count($arMovimientosDet) > 0) {
            $boolValidacion = false;
            foreach ($arMovimientosDet as $arMovimientosDet) {
                if ($OpReturn == 2 && $arMovimientosDet->Estado != 'AUTORIZADO') {
                    $strMensaje .= "El Item " . $arMovimientosDet->Id_Item . " - " . $arMovimientosDet->Descripcion . " tiene definida una vida util minima de (" . $arMovimientosDet->CantOperada . " meses) y la que se desea ingresar es inferior (" . $arMovimientosDet->Cantidad . " meses).<br />";
                } else if ($OpReturn != 2) {
                    $strMensaje .= "El Item " . $arMovimientosDet->Id_Item . " - " . $arMovimientosDet->Descripcion . " tiene definida una vida util minima de (" . $arMovimientosDet->CantOperada . " meses) y la que se desea ingresar es inferior (" . $arMovimientosDet->Cantidad . " meses).<br />";
                }
            }

            if ($strMensaje != "") {
                funciones::Mensaje($strMensaje, 2, $objVista);
            }
        }
        if ($OpReturn == 2) {
            return $strMensaje;
        } else {
            return $boolValidacion;
        }
    }

    /**
     * Envia un correo notificando los invimas vencidos
     * 
     * @param integer $intIdMovimiento Movimiento que contiene los items a analizar
     * @param Object $objVista la vista para sacar los mensajes
     * */
    public static function NotificacionCorreoInvimas($intIdMovimiento, $objVista) {
        $boolValidacion = true;
        $strSql = "SELECT movimientos_det.IdMovimientoDet, item.Descripcion, movimientos_det.Id_Item, lista_costos_prov_det.IdListaCostosProvDet as CantGarantia, lista_costos_prov.NmListaCostos as CantPrestamo
                    FROM movimientos_det
                    LEFT JOIN item ON movimientos_det.Id_Item = item.Id_Item
                    LEFT JOIN lista_costos_prov_det ON item.IdListaCostosDetItem = lista_costos_prov_det.IdListaCostosProvDet
                    LEFT JOIN lista_costos_prov ON lista_costos_prov_det.IdListaCostosProv = lista_costos_prov.IdListaCostosProv
                    WHERE IdMovimiento = " . $intIdMovimiento . " AND  (lista_costos_prov_det.NroRadicadoInvima is NULL  OR  lista_costos_prov_det.NroRadicadoInvima  = '') AND
                    (lista_costos_prov_det.FhVenceInvima < '" . date('Y-m-d') . "' OR lista_costos_prov_det.RegInvima = '' OR lista_costos_prov_det.RegInvima IS NULL) AND  (lista_costos_prov_det.CertAgotamientoEtiquetas is NULL  OR  lista_costos_prov_det.CertAgotamientoEtiquetas  = '')";


        $arMovimientosDet = new MovimientosDetRecord();
        $arMovimientosDet = MovimientosDetRecord::finder('MovimientosDetExtRecord')->FindAllBySql($strSql);

        $strMensaje = "Los siguientes items han sido ingresados a un movimiento el dia de hoy"
                . " y estos tienen el invima vencido  y no cuentan con radicado de renovacion o certificado de agotamiento de etiquetas, por favor verifiquelos y corrijalos <br /><br />";


        if (count($arMovimientosDet) > 0) {
            $boolValidacion = false;
            foreach ($arMovimientosDet as $arMovimientosDet) {
                $strMensaje .= "El Item " . $arMovimientosDet->Id_Item . " " . $arMovimientosDet->Descripcion . " tiene el invima vencido o no tiene, lista costos: " . $arMovimientosDet->CantPrestamo . ", IdListaCostosDetalle:" . $arMovimientosDet->CantGarantia . " <br />";
            }

            if ($strMensaje != "") {
                funciones::Mensaje($strMensaje, 2, $objVista);
            }

            $strMensaje .= "<br/>Por favor notificar cuando se actualice esta informacion";
            $arUsuario = new UsuarioRecord();
            $arUsuario = UsuarioRecord::finder()->findByPk($objVista->User->Name);
            $arConfiguraciones = new ConfiguracionesRecord();
            $arConfiguraciones = ConfiguracionesRecord::finder()->findAll();
            $Email[] = $arConfiguraciones[0]->EmailReporteInvimas;
            $Email[] = "regente@aba.com.co";
            $Email[] = 'auxcompras@aba.com.co';
            $Email[] = 'gestionproveedores@aba.com.co';
            funciones::EnviarCorreo(true, "kasten@aba.com.co", "Kasten - ABA Cientifica", "Invimas vencidos", $strMensaje, $Email);
        }
    }

    /**
     * Valida que las cantidades cotizadas sean mayores o iguales a las minimas de venta<br>
     * valida que no exista ningun margen Original en cero en la cotizacion.
     * @param objeto $objVista La vista desde donde se esta llamando el metodo.
     * @return boolean Retorna falso si se encuentra algun registro / true en caso contrario
     * */
    public static function ValidarMargenesCantidadesCotizacion($objVista) {
        $strSql = "SELECT IdCotizacionDet, CantidadCotizacion, lista_costos_prov_det.CantMinimaVenta, MargenOriginal, Habilitado
                   FROM cotizaciones_det LEFT JOIN lista_costos_prov_det ON cotizaciones_det.IdListaCostosDetCot = lista_costos_prov_det.IdListaCostosProvDet     
                   WHERE CantidadCotizacion < CantMinimaVenta AND MargenOriginal = 0 AND Margen = 0 AND IdCotizacion=" . $objVista->Request['IdCot'];

        $arDetalleCotizacion = new CotizacionesDetRecord();
        $arDetalleCotizacion = CotizacionesDetRecord::finder('CotizacionesDetExtRecord')->FindAllBySql($strSql);

        if (Count($arDetalleCotizacion) > 0) {
            funciones::Mensaje("No se puede cambiar el estado de la cotizacion, verifique:<br>
                      Si existen productos cuya cantidad minima de venta para el detalle  es mayor a la cantidad cotizada. <br>
                      Si el margen es igual a cero.<br>
                      Si Hay Productos sin Habilitar.", 2, $objVista);
            return false;
        } else
            return true;
    }

    /**
     * valida que no exista ningun factor de cliente en cero.
     * @param objeto $objVista La vista desde donde se esta llamando el metodo.
     * @return boolean Retorna falso si se encuentra algun registro / true en caso contrario
     * */
    public static function ValidarFactoresCeroCotizacion($objVista) {
        $strSql = "SELECT IdCotizacionDet
                   FROM cotizaciones_det 
                   WHERE (FactorCliente <= 0 OR FactorCliente IS NULL) AND IdCotizacion=" . $objVista->Request['IdCot'];

        $arDetalleCotizacion = new CotizacionesDetRecord();
        $arDetalleCotizacion = CotizacionesDetRecord::finder()->FindAllBySql($strSql);
        if (Count($arDetalleCotizacion) > 0) {
            funciones::Mensaje("Existen factores de cliente en cero o sin factor, no se puede cambiar el estado de la cotizacion", 2, $objVista);
            return false;
        } else {
            return true;
        }
    }

    /**
     * Cargar las horas en un combo
     *    
     * @return array $array Horas posibles    
     */
    public static function CargarHoras() {
        $arrayHoras = array('00' => '00', '01' => '01', '02' => '02', '03' => '03', '04' => '04', '05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09', '10' => '10',
            '11' => '11', '12' => '12', '13' => '13', '14' => '14', '15' => '15', '16' => '16', '17' => '17', '18' => '18', '19' => '19', '20' => '20', '21' => '21',
            '22' => '22', '23' => '23');
        return $arrayHoras;
    }

    /**
     * Cargar las minutos en un combo
     *    
     * @return array $array Minutos posibles    
     */
    public static function CargarMinutos() {
        $arrayMinutos = array('00' => '00', '01' => '01', '02' => '02', '03' => '03', '04' => '04', '05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09', '10' => '10',
            '11' => '11', '12' => '12', '13' => '13', '14' => '14', '15' => '15', '16' => '16', '17' => '17', '18' => '18', '19' => '19', '20' => '20', '21' => '21',
            '22' => '22', '23' => '23', '24' => '24', '25' => '25', '26' => '26', '27' => '27', '28' => '28', '29' => '29', '30' => '30', '31' => '31', '32' => '32',
            '33' => '33', '34' => '34', '35' => '35', '36' => '36', '37' => '37', '38' => '38', '39' => '39', '40' => '40', '41' => '41', '42' => '42', '43' => '43',
            '44' => '44', '45' => '45', '46' => '46', '47' => '47', '48' => '48', '49' => '49', '50' => '50', '51' => '51', '52' => '52', '53' => '53', '54' => '54',
            '55' => '55', '56' => '56', '57' => '57', '58' => '58', '59' => '59');
        return $arrayMinutos;
    }

    /**
     * Cargar Si NO en un combo
     *    
     * @return array $array 
     */
    public static function CargarSiNO() {
        $arrayOp = array('SI' => 'SI', 'NO' => 'NO');
        return $arrayOp;
    }

    /**
     * Cargar Si,No,Lic
     * @return string
     */
    public static function CargarSiNOLic() {
        $arrayOp = array('SI' => 'SI', 'NO' => 'NO', 'LIC' => 'LIC');
        return $arrayOp;
    }

    /**
     * Cargar Areas de servicio
     *    
     * @return array $array Horas posibles    
     */
    public static function AreaServicio() {
        $arrayTPSevicio = array('TIC' => 'TIC', 'INGENIERIA' => 'INGENIERIA', 'SOPORTE COMERCIAL' => 'SOPORTE COMERCIAL', 'GESTION DE VENTA' => 'GESTION DE VENTA');
        return $arrayTPSevicio;
    }

    /**
     * Cargar Proceso Novedades
     *    
     * @return array $array Horas posibles    
     */
    public static function ProcesoNovedades() {
        $arrayTPSevicio = array('RECEPCION' => 'RECEPCION', 'ALMACENAMIENTO' => 'ALMACENAMIENTO', 'DESPACHO' => 'DESPACHO', 'ENTREGA' => 'ENTREGA', 'DEVOLUCION' => 'DEVOLUCION', 'FACTURACION-PEDIDO' => 'FACTURACION-PEDIDO', 'VENTA-POSVENTA' => 'VENTA-POSVENTA', 'CARTERA' => 'CARTERA', 'COTIZACION-LICITACION' => 'COTIZACION-LICITACION', 'COMPRAS' => 'COMPRAS', 'OTRAS GESTIONES ADMINISTRATIVAS' => 'OTRAS GESTIONES ADMINISTRATIVAS');
        return $arrayTPSevicio;
    }

    /**
     * Devuelve un array con los estados para un documento segun el tipo enviado por parametro
     * @param integer $intTipo
     * 1 -> Array de estados para los documentos operativos
     * 2 -> Array de estados para los contratos
     * 3 -> Array de estados para los cambios de precios
     * @return array $arrayEstados Array con los estados
     * */
    public static function DevEstados($intTipo) {
        switch ($intTipo) {
            case 1:
                $arrayEstados = array('DIGITADA' => 'DIGITADA', 'AUTORIZADA' => 'AUTORIZADA', 'CERRADA' => 'CERRADA', 'ANULADA' => 'ANULADA');
                break;

            case 2:
                $arrayEstados = array('DIGITADO' => 'DIGITADO', 'EJECUCION' => 'EJECUCION', 'CUMPLIDO' => 'CUMPLIDO', 'PAGADO' => 'PAGADO', 'PRECONTRATO' => 'PRECONTRATO');
                break;

            case 3:
                $arrayEstados = array('DIGITADA' => 'DIGITADA', 'AUTORIZADA' => 'AUTORIZADA', 'CERRADA' => 'CERRADA', 'ANULADA' => 'ANULADA');
                break;
            //Licitaciones
            case 4:
                $arrayEstados = array('DIGITADA' => 'DIGITADA');
                break;
        }

        return $arrayEstados;
    }

    /**
     * Valida que la fecha una fecha inicial de vigencia sea menor a la fecha final.
     * @param date $dateFechaInicial La fecha desde la que comienza una vigencia
     * @param date $dateFechaFinal La fecha hasta la que llega una vigencia
     * @param objeto $objVista La vista actual.
     * @return boolean falso en caso que la fecha inicial sea mayor que la de final / true en caso contrario
     * */
    public static function ValidarValidezFechas($dateFechaInicial, $dateFechaFinal, $objVista) {
        $Total = self::restaFechas($dateFechaInicial, $dateFechaFinal);
        if ($Total < 0) {
            funciones::Mensaje("La fecha inicial no puede ser mayor a la fecha final.", 2, $objVista);
            return false;
        } else
            return true;
    }

    /**
     * Valida si un documento se encuentra o no asociado a un contrato
     * @param integer $intIdContrato El Id del contrato al que se desea enlazar el documento
     * @param objet $objVista La vista actual
     * @return boolean true en caso que este asociado o que no necesariamente lo debe estar(depende de la parametrizacion del tercero o si el usuario no necesita enlazar a contrato)
     *  / falso si no esta asociado a un contrato
     */
    public static function ValidarContrato($intIdContrato, $objVista) {
        // Valida si el documento esta o no enlazado a un contrato.
        $intIdMovimiento = $objVista->Request['IdMov'];
        $arMovimiento = new MovimientosRecord();
        $arMovimiento = MovimientosRecord::finder()->FindByPk($intIdMovimiento);
        if ($arMovimiento->IdTercero > 0 && $arMovimiento->IdMovimiento > 0) {
            $ValItemsContrato = new ContratosDetRecord();
            $Sql = "select contratos.IdContrato,contratos_det.Id_Item from contratos_det 
                    LEFT JOIN contratos on contratos.IdContrato = contratos_det.IdContrato
                    LEFT JOIN movimientos_det on movimientos_det.Id_Item = contratos_det.Id_Item
                    where contratos.IdContratante = " . $arMovimiento->IdTercero . "  and IdMovimiento = " . $arMovimiento->IdMovimiento . " and (contratos.Estado='DIGITADO' OR contratos.Estado='EJECUCION' OR contratos.Estado='PRECONTRATO') GROUP BY Id_Item,IdContrato";
            $ValItemsContrato = ContratosDetRecord::finder()->FindAllBySql($Sql);
            $MensajeAdd = "";
            if (count($ValItemsContrato) > 0) {
                $MensajeAdd = "<br> Los sigueintes items estan en los siguientes contratos para el cliente:IdContrato - IdItem <br>";
                foreach ($ValItemsContrato as $Row) {
                    $MensajeAdd .= $Row->IdContrato . " - " . $Row->Id_Item . " /";
                }
            }
        }
        if ($intIdContrato == NULL && $objVista->getViewState('SinContrato') == false && $arMovimiento->NoAplicaContrato == 0) {
            funciones::Mensaje("El documento no se encuentra asociado a ningun contrato, desea continuar?." . $MensajeAdd, 2, $objVista);
            $objVista->BtnContinuarSinContrato->Visible = true;
            $objVista->TxtComentarios->Visible = true;
            $objVista->TxtComentarios->Focus();
            return false;
        } else
            return true;
    }

    /**
     * Valida si el usuario es de tipo externo y redirecciona el navegador si los es
     * @param string $strUsuario El usuario actual
     * @param objeto $objVista  La vista actual
     * */
    public static function ValidarUsuarioExterno($strUsuario, $objVista) {
        $arUsuario = new UsuarioRecord;
        $arUsuario = UsuarioRecord::finder()->FindByPk($strUsuario);

        if ($arUsuario->TipoExterno == 1)
            funciones::IrA("?page=clientes.Home", $objVista);
    }

    /**
     * Carga a un array los conceptos de prioridades 
     * @return array $arrayPrioridades Array con los conceptos de prioridades.
     * */
    public static function CargarPrioridades() {
        $arrayPrioridades = array('1' => 'ALTA', '2' => 'NORMAL', '3' => 'MEDIA', '4' => 'BAJA');
        return $arrayPrioridades;
    }

    /**
     * Elimina recursivamente un directorio
     * @author Cristián Pérez
     * @param string $path la ruta del directorio a eliminar
     * @link http://www.cristianperez.com/2010/01/05/borrar-un-directorio-no-vacio-con-php/
     * */
    public static function eliminar_directorio($strPath) {
        $strPath = rtrim(strval($strPath), DIRECTORY_SEPARATOR);

        $strDirectorio = dir($strPath);

        if (!$strDirectorio)
            return false;

        while (false !== ($DirActual = $strDirectorio->read())) {
            if ($DirActual === '.' || $DirActual === '..')
                continue;

            $file = $strDirectorio->path . '/' . $DirActual;

            if (is_dir($file))
                $this->rmdir_recurse($file);

            if (is_file($file))
                unlink($file);
        }

        rmdir($strDirectorio->path);
        $strDirectorio->close();
    }

    /**
     * Crear un archivo excel en la carpeta temporal de los documentos
     * establecida en la entidad configuracion.
     */
    public static function CrearExcelTemporal($strNombreArchivo, $arrayDatos) {
        $arConfiguracion = new ConfiguracionesRecord();
        $arConfiguracion = ConfiguracionesRecord::finder()->findAll();

        $Archivo = "xlsfile://tmp/" . $strNombreArchivo;
        chmod($Archivo, 777);

        $fp = fopen($Archivo, "wb");

        if (!is_resource($fp)) {
            die("Error al crear $Archivo");
        }

        fwrite($fp, serialize($arrayDatos));

        fclose($fp);

        return str_replace("xlsfile:/", "", $Archivo);
    }

    /**
     * Devuelve un texto con el formato 'Texto%Busqueda', para mejorar los resultados de las busquedas
     * evitando que el usuario deba escribir el simbolo
     * @param string $strCadena Cadena de texto con espacios que digita el usuario
     * @return string Cadena de texto reemplazando los espacios por el comodin % 
     */
    public static function DevCadenaBusqueda($strCadena) {
        $strCadena = str_replace(" ", "%", $strCadena);
        return $strCadena;
    }

    /**
     * Busca la informaicion perteneciente a una actividad economica buscado por 
     * id o por descricion, y retorna los datos en forma de lista al autocomplete desde el que 
     * este metodo es llamado.
     * @param type $sender
     * @param type $param
     * @param type $Todos Variable que determina si muestra un listado de actividades.
     * */
    public static function BuscarActividad($sender, $param) {

        $strSql = "SELECT DescripcionActividadEconomica,IdActividadEconomica 
                FROM actividades_economicas
                WHERE 
                (DescripcionActividadEconomica LIKE '%" . $sender->Data . "%' OR IdActividadEconomica LIKE '%" . $sender->Data . "%')";

        $strSql = $strSql . " LIMIT 5";

        $actividades = ActividadesEconomicasRecord::finder()->findAllBySql($strSql);

        $list = array();

        foreach ($actividades as $row)
            $list[] = $row->IdActividadEconomica . "_" . $row->DescripcionActividadEconomica;

        $sender->setDataSource($list);
        $sender->dataBind();
    }

    /**
     * Devuelve el ultimo dia del mes indicado en numero
     * @param type $sender
     * @param type $param
     * @param type date.
     * */
    public static function UltimoDiaMes($Anio, $Mes) {
        return date("d", (mktime(0, 0, 0, $Mes + 1, 1, $Anio) - 1));
    }

    /**
     * Envia notificacion de la accion realizada a un documento
     * 
     * @param integer $IdDocumento Id del documento
     * @param integer $IdAccion indica el tipo de accion 
     * @param integer $IdMovimiento indica el id movimietno del documento para consultar los datos de esteduy
     * @param integer $IdItem valida si se cerraron items para agregarle texto al correo
     * @param varchar $Comentarios valida si la variable viene con algun comentario y lo anexa al correo 
     */
    public static function NotificarAccion($IdDocumento, $IdAccion, $IdMovimiento, $Usuario, $IdItem = '', $Comentarios = '', $Adjunto = '') {

        include_once("class.phpmailer.php");
        include_once("class.smtp.php");

        $arNotificacion = NotificacionesDocumentosRecord::ConsultaNotificacionDocumento($IdDocumento, $IdAccion);

        if (count($arNotificacion) > 0) {
            $IdTercero = "";
            $IDCCto = "";
            $IdDir = "";
            $Cont = 0;
            if ($IdDocumento != 2) {
                $Mov = new MovimientosRecord();
                $Mov = MovimientosRecord::finder()->FindByPk($IdMovimiento);
                $IdTercero = $Mov->IdTercero;
                $IDCCto = $Mov->IdContrato;
                $IdDir = $Mov->IdDireccion;
            } else if ($IdDocumento == 2) {
                $Mov = new CotizacionesRecord();
                $Mov = CotizacionesRecord::finder()->FindByPk($IdMovimiento);
                $IdTercero = $Mov->IdTerceroCotizacion;
                $IdDir = $Mov->IdDireccionCotizacion;
            }

            foreach ($arNotificacion as $arNotificacion) {
                $ValidTer = false;
                $IGualTer = false;
                $ValidaDir = false;
                $IgualDir = false;
                $ValidaCC = false;
                $IgualCC = false;
                if ($arNotificacion->IdTercero > 0) {
                    $ValidTer = true;
                    if ($arNotificacion->IdTercero == $IdTercero) {
                        $IGualTer = true;
                    }
                }
                if ($arNotificacion->IdDireccion > 0) {
                    $ValidaDir = true;
                    if ($arNotificacion->IdDireccion == $IdDir) {
                        $IgualDir = true;
                    }
                }
                if ($arNotificacion->IdContrato > 0) {
                    $ValidaCC = true;
                    if ($arNotificacion->IdContrato == $IDCCto) {
                        $IgualCC = true;
                    }
                }
                if (($ValidTer == true && $IGualTer == true) || ($ValidaDir == true && $IgualDir == true) || ($ValidaCC == true && $IgualCC == true) || $ValidTer == false && $ValidaDir == false && $ValidaCC == false) {
                    $strAsunto = '';
                    $strAccion = '';
                    if ($IdDocumento != 2) {
                        $strSql = "SELECT
                        movimientos.IdMovimiento,
                        movimientos.IdDocumento,
                        terceros.IdTercero,
                        terceros.NombreCorto,
                        movimientos.NroDocumento,
                        documentos.Nombre as NmDocumento,usuarios.Email as Email,IdUsuario
                        FROM
                        movimientos
                        INNER JOIN documentos ON movimientos.IdDocumento = documentos.IdDocumento
                        INNER JOIN terceros ON movimientos.IdTercero = terceros.IdTercero 
                        INNER JOIN  usuarios on usuarios.Usuario =  movimientos.IdUsuario
                        WHERE IdMovimiento=" . $IdMovimiento;

                        $Datos = MovimientosDetRecord::finder('MovimientosDetExtRecord')->FindAllBySql($strSql);
                    }
                    //Si es una cotizacion trae los datos de la misma 
                    else if ($IdDocumento == 2) {
                        $strSql = "SELECT
                        cotizaciones.IdCotizacion,
                        cotizaciones.IdDocumento,
                        terceros.IdTercero,
                        terceros.NombreCorto,
                        cotizaciones.NroCotizacion,
                        documentos.Nombre as NmDocumento,usuarios.Email as Email,usuarios.Usuario as IdUsuario
                        FROM
                        cotizaciones
                        INNER JOIN documentos ON cotizaciones.IdDocumento = documentos.IdDocumento
                        INNER JOIN terceros ON cotizaciones.IdTerceroCotizacion = terceros.IdTercero 
                        INNER JOIN  usuarios on usuarios.Usuario =  cotizaciones.Usuario
                        WHERE IdCotizacion = " . $IdMovimiento;
                        $Datos = CotizacionesRecord::finder('CotizacionesExtRecord')->FindAllBySql($strSql);
                    }
                    $NombreDoc = $Datos[0]->NmDocumento;
                    $NroDocumento = $Datos[0]->NroDocumento;
                    if ($IdDocumento == 2 && count($Datos) > 0) {
                        $NroDocumento = $Datos[0]->NroCotizacion;
                    }
                    switch ($IdAccion) {
                        case 1: //Anular
                            $strAsunto = 'Anulacion en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Anular';
                            break;

                        case 2: //Autorizar
                            $strAsunto = 'Autorizacion en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Autorizar';
                            break;

                        case 3: //Des-Autorizar
                            $strAsunto = 'Des-Autorizacion en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Des-Autorizar';
                            break;

                        case 4: //Cerrar
                            $strAsunto = 'Cierre en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Cerrar';
                            break;

                        case 5: //Abrir
                            $strAsunto = 'Abrir en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Abrir';
                            break;

                        case 6: //Enviar Pendiente
                            $strAsunto = 'Envio de Pendiente en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Enviar Pendiente';
                            break;

                        case 7: //Imprimir
                            $strAsunto = 'Impresion en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Imprimir';
                            break;

                        case 8: //Nuevo Registro
                            $strAsunto = 'Nuevo Registro en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Nuevo Registro';
                            break;

                        case 12: //Eliminar Item
                            $strAsunto = 'Eliminar Item en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Eliminar Item Registro';
                            break;

                        case 19: //Eliminar Item General
                            $strAsunto = 'Eliminar Item en ' . $NombreDoc . ' Nro ' . $NroDocumento;
                            $strAccion = 'Eliminar Item Registro';
                            break;
                    }

                    $strMensaje = "<br>
                        <br>
                        Este es un nuevo mensaje enviado desde ABA Cientifica, el cual le informa que en la fecha y hora " . date("d/m/Y, g:i a") . " 
                        se realizo la accion de " . $strAccion . " por el usuario(a) " . $Usuario . " en el documento '" . $Datos[0]->NmDocumento . "' Nro " . $NroDocumento . " del cliente :" . $Datos[0]->NombreCorto . ", 
                        si desea realizarle seguimiento a esta accion busque el documento y verifique en el sistema Kasten.<br><br>";
                    if ($IdItem != '') {
                        $item = new ItemRecord();
                        $Item = ItemRecord::finder()->findByPk($IdItem);
                        $strMensaje = $strMensaje . "Los items  afectados son:<br><br>";
                        $strMensaje = $strMensaje . "<table border='1'>"
                                . "<tr><td><b>ID ITEM</b></td><td><center><b>DESCRIPCIÓN</b></center></td>"
                                . "<tr><td>" . $IdItem . "</td><td>" . $Item->Descripcion . "</td></tr></table>";
                    }
                    if ($Comentarios != '') {
                        $strMensaje = $strMensaje . "Comentarios del usuario sobre la acción de cerrar :" . $Comentarios;
                    }

                    //Creamos la tabla con los detalles de la cot.
                    if ($IdDocumento == 2) {
                        $Det = new CotizacionesDetRecord();
                        $Sql = "select cotizaciones_det. * , lista_costos_prov_det.RefFabricante,UMVCot,PorIvaCotizacion,lista_costos_prov_det.CantMinimaVenta  from cotizaciones_det
                                    LEFT JOIN cotizaciones on cotizaciones.IdCotizacion = cotizaciones_det.IdCotizacion
                                    LEFT JOIN lista_costos_prov_det on lista_costos_prov_det.IdListaCostosProvDet = cotizaciones_det.IdListaCostosDetCot
                                    LEFT JOIN terceros on terceros.IdTercero = cotizaciones.IdTerceroCotizacion
                                    where cotizaciones.IdCotizacion = " . $IdMovimiento;
                        $Det = CotizacionesDetRecord::finder("CotizacionesDetExtRecord")->FindAllBySql($Sql);
                        $strMensaje = $strMensaje . "<br><table border='1'>"
                                . "<tr><td><b>ID ITEM</b></td><td><center><b>DESCRIPCIÓN</b></center></td><td><b>REF. FABRICANTE</b></td><td><b>VIG. PRECIO</b></td><td><b>UND MEDIDA</b></td><td><b>VLR. UND MEDIDA</b></td><td><b>IVA</b></td><td><b>CANT. MIN. VENTA</b></td><td><b>CANT. COTIZADA</b></td></tr>";
                        foreach ($Det as $Det) {
                            $strMensaje = $strMensaje . "<tr><td>" . $Det->IdItemCotizacion . "</td><td>" . $Det->DescripcionCotizacion . "</td><td>" . $Det->RefFabricante . "</td><td>" . $Det->FhHastaPrecioCot . "</td><td>" . $Det->UMVCot . "</td><td Style='text-align: right;'>" . number_format($Det->PrecioCotizacion, 2, '.', ',') . "</td><td Style='text-align: right;'>" . $Det->PorIvaCotizacion . "</td><td Style='text-align: right;'>" . $Det->CantMinimaVenta . "</td><td Style='text-align: right;'>" . $Det->CantidadCotizacion . "</td></tr>";
                        }
                        $strMensaje = $strMensaje . "</table>";
                    }
                    $strMensaje = $strMensaje . "<br>
                       
                        <br>
                        Muchas gracias por la atención,
                        <br> 
                        <br>
                        <br> 
                        Este correo es enviado automaticamente desde el sistema, por favor absténgase de responderlo, cualquier inquietud comuniquese con sistemas@aba.com.co - auxsistemas@aba.com.co";


                    //Si el documento es solicitud de devolucion envia el correo a la dueña del documento.
                    if ($Datos[0]->IdUsuario != $Usuario && $arNotificacion->Email == '') {
                        $strEmail = $Datos[0]->Email;
                    } else {
                        //Creamos un array con los correos a los cuales les debe notificar la accion anteriormente solamente se lo enviaba a un solo correo.
                        $strEmail[] = '';
                        //   foreach ($arNotificacion as $Emails) {
                        $strEmail[] = $arNotificacion->Email;
                        //  }
                        //Si es una cotizacion le envia  el correo a el asesor.
                        if ($IdDocumento == 2) {
                            if ($Cont == 0) {
                                $Cot = new CotizacionesRecord();
                                $Cot = CotizacionesRecord::finder()->with_Tercero()->FindByPK($IdMovimiento);
                                $Asesor = new AsesoresRecord();
                                if ($Cot->AsesorCotizacion != '') {
                                    $Asesor = AsesoresRecord::finder()->findByPk($Cot->AsesorCotizacion);
                                } else {
                                    $Asesor = AsesoresRecord::finder()->findByPk($Cot->Tercero->IdAsesor);
                                }
                                $AsesorAba = AsesoresRecord::finder()->findByPk($Cot->Tercero->IdAsesorServicliente);
                                $strEmail[] = $Asesor->Email;
                                $strEmail[] = $AsesorAba->Email;
                            }
                        }
                    }
                    if ($Adjunto != '') {
                        funciones::EnviarCorreo(false, "kasten@aba.com.co", "Kasten - ABA Cientifica", $strAsunto, $strMensaje, $strEmail, $Adjunto);
                    } else {
                        funciones::EnviarCorreo(false, "kasten@aba.com.co", "Kasten - ABA Cientifica", $strAsunto, $strMensaje, $strEmail);
                    }
                }
                unset($strEmail); //Borra los email del array para que no llegue el correo 2 veces.
                $Cont++;
            }
        }
    }

    public function RestarMeses() {
        $FechaFin = date('Y-m-j');
        $FechaInicio = strtotime('-6 month', strtotime($FechaFin));
        $FechaInicio = date('Y-m-j', $FechaInicio);
        return $FechaInicio;
    }

    public function DevMes($mes, $anio) {

        for ($i = 1; $i <= 4; $i++) {
            $mes = $mes - 1;
            if ($mes == 0) {
                $mes = 12;
                $anio = $anio - 1;
            }
            //echo $mes." " . $anio . "<br/>";
        }

        $intMesDev = $mes - 4;
        $dateFechaInicial = $anio . "/" . $mes . "/01";
        return $dateFechaInicial;
    }

    //Devuelve el valor de promedio ventas de 4 meses de un item
    public function PromedioVentaMeses($FechaInicial, $FechaFinal, $IdItem, $CantMeses) {


        $Promedio = new MovimientosDetRecord();
        $sql = " SELECT  Fecha,SUM(movimientos_det.CantOperada * -1) AS CantOperada   FROM   movimientos_det
                 LEFT JOIN movimientos on movimientos.IdMovimiento = movimientos_det.IdMovimiento
                WHERE
                movimientos.IdDocumento = 3
                AND (movimientos.Estado = 'AUTORIZADA' OR movimientos.Estado = 'CERRADA')
                AND DATE_FORMAT(Fecha,'%Y-%m-%d') >='" . $FechaInicial . "' and DATE_FORMAT(Fecha,'%Y-%m-%d') <='" . $FechaFinal . "'
                AND movimientos_det.Id_Item = " . $IdItem . "
                AND Impresion > 0";
        $Promedio = MovimientosDetRecord::finder('MovimientosDetExtRecord')->FindAllBySql($sql);
        if ($Promedio[0]->CantOperada > 0) {
            $PromedioFinal = round($Promedio[0]->CantOperada / $CantMeses);
            return $PromedioFinal;
        } else {
            return 0;
        }
    }

    //Consultar la existencia de un producto.
    public function ValidarExistenciaItem($IdItem) {
        $Item = new ItemRecord();
        $Sql = "SELECT item.Id_Item, item.Existencia, item.Descripcion, item.Remisionada, 
               item.Disponible, item.Reserva, item.CantOC, item.CantPedido, 
               lista_costos_prov_det.RefFabricante as Referencia
           FROM item 
               LEFT JOIN lista_costos_prov_det ON item.IdListaCostosDetItem = lista_costos_prov_det.IdListaCostosProvDet
               WHERE item.Id_Item =" . $IdItem;
        $IdItem = ItemRecord::finder('ItemExtRecord')->FindAllBySql($Sql);
        if ($IdItem[0]->Existencia > 0) {
            return $IdItem[0]->Existencia;
        } else {
            return 0;
        }
    }

    /*
     * Descripcion: Se crea la funcion ValidarTipoArchivo(), para retornar el tipo de extencion que debe llevar el archivo al descargarlo en windows.
     * Param:$Mime.
     * Return:Tipo archivo-aplicacion.
     * FhActualizacion:11/04/2016.
     */

    public function ValidarTipoArchivo($Mime) {
        $Row = '';
        switch ($Mime) {
            case $Mime === 'image/png';
                $Row = 'png-image/png';
                return $Row;
                break;

            case $Mime === 'image/jpeg';
                $Row = 'jpeg-image/jpeg';
                return $Row;
                break;

            case $Mime === 'image/jpeg';
                $Row = 'jpeg-image/jpeg';
                return $Row;
                break;

            case $Mime === 'image/gif';
                $Row = 'gif-image/gif';
                return $Row;
                break;

            case $Mime === 'application/pdf';
                $Row = 'pdf-application/pdf';
                return $Row;
                break;

            case $Mime === 'application/octet-stream';
                $Row = 'pdf-application/pdf';
                return $Row;
                break;

            case $Mime === 'application/vnd.ms-office';
                $Row = "xls-application/vnd.ms-excel";
                return $Row;
                break;
        }
    }

    /**
     * Elimina recursivamente un directorio
     * @author Cristián Pérez
     * @param string $path la ruta del directorio a eliminar
     * @link http://www.cristianperez.com/2010/01/05/borrar-un-directorio-no-vacio-con-php/
     * Fhactualizacion:26/04/2016.
     * */
    public function rmdir_recurse($strPath, $objVista) {
        $strPath = rtrim(strval($strPath), '/');
        $strDirectorio = dir($strPath);
        if (!$strDirectorio)
            return false;
        while (false !== ($DirActual = $strDirectorio->read())) {
            if ($DirActual === '.' || $DirActual === '..')
                continue;
            $file = $strDirectorio->path . '/' . $DirActual;
            if (is_dir($file))
                $objVista->rmdir_recurse($file);
            if (is_file($file))
                unlink($file);
        }
        rmdir($strDirectorio->path);
        $strDirectorio->close();
    }

    /*
     * Descripcion: Se crea funcion DescargarComprimido, para descargar la carpeta temporal creada en el servidor.
     * Param:Nombre del achivo.
     * Return:No aplica.
     * FhActualizacion:26/04/2016.
     */

    public function DescargarComprimido($NombreArchivo) {
        $Server = $_SERVER["HTTP_HOST"];
        $this->Response->Redirect("http://$Server/TempDocumentos$NombreArchivo.tar");
    }

    /*
     * Descripcion: Se crea funcion abrirVentana, para Abrir un documento en una ventana emergente.
     * Param:url.
     * Return:No aplica.
     * FhActualizacion:16/09/2016.
     */

    public function abrirVentana($url) {
        $var = $url;
        echo "<script language='javascript'>window.open('$var' ,'_blank','','toolbar=0,width=1000,height=800,location=0,status=1,menubar=0,scrollbars=1,resizable=0')</script>";
    }

    public static function CargarDetallesPlantilla($Plantilla, $objVista, $OpNoEnviados = false, $SqlAdicion = '') {
        $PlantillaDet = new PlantillasDetRecord();
        $Sql = "select plantillas_det.* ,lista_costos_prov_det.Id_Item,lista_costos_prov_det.FactorCompra as Factor,item.UMM,if(plantillas_det.CProximo=1,plantillas_det.CostoUMMProximo,lista_costos_prov_det.CostoUMM) as CostoUMM,lista_costos_prov_det.Costo,(CASE WHEN plantillas_det.Autorizado IS NULL THEN '' WHEN plantillas_det.Autorizado =0 THEN 'NO' WHEN plantillas_det.Autorizado =1 THEN 'SI' END)  as Autorizado,if(AceptaAlternativa =1,'SI','NO') as AceptaAlternativa,(CASE Alternativa WHEN NULL THEN '' WHEN 1 THEN 'SI' WHEN 0 THEN 'NO' END) Alternativa ,lista_costos_prov_det.DescripcionProv,NmMarca,terceros.IdTercero,terceros.NombreCorto,if(lista_costos_prov_det.HabCotizar=1,'SI','NO') as HabCotizar,CONCAT(lista_costos_prov.NmListaCostos,': ',if(plantillas_det.CProximo=1,lista_costos_prov_det.FhDesdeCostoProx,lista_costos_prov_det.FhDesde) ,' - ',if(plantillas_det.CProximo=1,lista_costos_prov_det.FhHastaCostoProx,lista_costos_prov_det.FhHasta)) as NmListaCostos,
                lista_costos_prov_det.CategoriaPortafolio,lista_costos_prov_det.UMV,lista_costos_prov_det.Costo/FactorCliente as CostoUMV,if(Revisado=1,'SI','NO') as Revisado,lista_costos_prov_det.UMC,lista_costos_prov_det.RefFabricante as Referencia, lista_costos_prov_det.Presentacion
                ,lista_costos_prov_det.ClasificacionProveedor,cliente.NombreCorto as Cliente,lista_costos_prov_det.IdListaCostosProvDet,lista_costos_prov_det.CodProveedor,ListaDet.CostoUMM
                ,round(((((plantillas_det.PrecioTecho)-lista_costos_prov_det.Costo) / lista_costos_prov_det.Costo) * 100),2) as VariacionCGen
                ,round(((((plantillas_det.CostoUMMPlantilla)-plantillas_det.CostoUMMLista) / plantillas_det.CostoUMMLista) * 100),2) as Variacion
                ,marcas.NmMarca,lista_costos_prov_det.UMC,lista_costos_prov_det.Costo,count(item.Id_Item) as Repetidos
                from plantillas_det
                LEFT JOIN lista_costos_prov_det  on lista_costos_prov_det.IdListaCostosProvDet = plantillas_det.IdListaCostosDetPlantDet  
                LEFT JOIN item on item.Id_Item = lista_costos_prov_det.Id_Item
                LEFT JOIN lista_costos_prov_det as ListaDet on ListaDet.IdListaCostosProvDet = item.IdListaCostosDetItem
                LEFT JOIN marcas on marcas.IdMarca = lista_costos_prov_det.IdMarca
                LEFT JOIN lista_costos_prov on lista_costos_prov.IdListaCostosProv = lista_costos_prov_det.IdListaCostosProv
                LEFT JOIN lista_costos_prov as ListaProv on ListaProv.IdListaCostosProv = ListaDet.IdListaCostosProv
                LEFT JOIN terceros on terceros.IdTercero = if(lista_costos_prov_det.IdListaDetReferencia is NULL,lista_costos_prov.IdTercero,ListaProv.IdTercero)
                LEFT JOIN terceros as cliente on cliente.IdTercero = plantillas_det.IdTerceroCliente
                where plantillas_det.IdPlantilla =" . $Plantilla . "";

        if ($OpNoEnviados == false) {
            if (isset($objVista->ChkHabilitadoLista)) {
                if ($objVista->ChkHabilitadoLista->Checked)
                    $Sql = $Sql . " AND HabCotizar > 1";
            }
            if ($objVista->CboFltMarcas->SelectedValue != "") {
                $Sql = $Sql . " AND  lista_costos_prov_det.IdMarca=" . $objVista->CboFltMarcas->SelectedValue;
            } elseif ($objVista->getViewState('Marca') != '') {
                $Sql = $Sql . " AND  lista_costos_prov_det.IdMarca=" . $objVista->getViewState('Marca');
            }

            if ($objVista->CboFltListas->SelectedValue != "") {
                $Sql = $Sql . " AND  lista_costos_prov_det.IdListaCostosProv=" . $objVista->CboFltListas->SelectedValue;
            } elseif ($objVista->getViewState('IdLista') != '') {
                $Sql = $Sql . " AND  lista_costos_prov_det.IdListaCostosProv=" . $objVista->getViewState('IdLista');
            }

            if ($objVista->CboFltLineas->SelectedValue != "") {
                $Sql = $Sql . " AND item.IdLinea=" . $objVista->CboFltLineas->SelectedValue;
            } elseif ($objVista->getViewState('IdLinea') != '') {
                $Sql = $Sql . " AND item.IdLinea=" . $objVista->getViewState('IdLinea');
            }

            if ($objVista->CboFltCategorias->SelectedValue != "") {
                $Sql = $Sql . " AND lista_costos_prov_det.CategoriaPortafolio='" . $objVista->CboFltCategorias->SelectedValue . "'";
            } elseif ($objVista->getViewState('CatPortafolio') != '') {
                $Sql = $Sql . " AND lista_costos_prov_det.CategoriaPortafolio='" . $objVista->getViewState('CatPortafolio') . "'";
            }

            if ($objVista->CboGrupos->SelectedValue != '') {
                $Sql = $Sql . " AND Grupo = '" . $objVista->CboGrupos->SelectedValue . "'";
            } elseif ($objVista->getViewState('Grupo') != '') {
                $Sql = $Sql . " AND Grupo = '" . $objVista->getViewState('Grupo') . "'";
            }

            if ($objVista->CboOpcion->SelectedValue != '') {
                if ($objVista->CboOpcion->SelectedValue == 0) {
                    $Sql = $Sql . " AND Opcion is null";
                } else {
                    $Sql = $Sql . " AND Opcion = '" . $objVista->CboOpcion->SelectedValue . "'";
                }
            } elseif ($objVista->getViewState('Opcion') != '') {
                $Opcion = $objVista->getViewState('Opcion');
                if ($Opcion == 0) {
                    $Sql = $Sql . " AND Opcion is Null";
                } else {
                    $Sql = $Sql . " AND Opcion =" . $Opcion;
                }
            }

            if ($objVista->CboAutorizados->SelectedValue != '') {
                $Autorizado = $objVista->CboAutorizados->SelectedValue;
                if ($objVista->CboAutorizados->SelectedValue == 2) {
                    $Sql = $Sql . " AND Autorizado is null";
                } else {
                    $Sql = $Sql . " AND Autorizado = " . $Autorizado;
                }
            } else if ($objVista->getViewState('Autorizado') != '') {
                $Autorizado = $objVista->getViewState('Autorizado');
                if ($objVista->getViewState('Autorizado') == 2) {
                    $Sql = $Sql . " AND Autorizado is Null";
                } else {
                    $Sql = $Sql . " AND Autorizado = '" . $objVista->getViewState('Autorizado') . "'";
                }
            }

            if ($objVista->ChkMargenEspecial->checked == true) {
                $Sql = $Sql . " AND  lista_costos_prov_det.MargenEspecial = 1";
            }

            if ($objVista->ChkFltSoloEnlazados->checked == true) {
                $Sql = $Sql . " AND  plantillas_det.IdListaCostosDetPlantDet > 0";
            }

            if ($objVista->ChkFltSoloNoEnlazados->checked == true) {
                $Sql = $Sql . " AND  plantillas_det.IdListaCostosDetPlantDet is NULL";
            }

            if ($objVista->ChkCantidades->Checked == true) {
                $Sql = $Sql . " AND  CantidadConsumo < lista_costos_prov_det.CantMinimaVenta";
            }

            if ($objVista->ChkNetosCeros->Checked == true) {
                $Sql = $Sql . " AND  plantillas_det.SubTotal = 0";
            }

            if ($objVista->ChkFhVencidas->Checked == true) {
                $Sql = $Sql . " AND  (FhHastaLista < '" . date('Y-m-d') . "')";
            }

            if ($objVista->TxtIdItem->Text != '') {
                $Sql = $Sql . " AND  lista_costos_prov_det.Id_Item = " . $objVista->TxtIdItem->Text;
            } else {
                $objVista->TxtIdItem->Text = "";
            }

            if ($objVista->TxtComentarioInterno->Text != '') {
                $Sql = $Sql . " AND  ComentariosCliente LIKE '%" . $objVista->TxtComentarioInterno->Text . "%'";
            }

            if ($objVista->TxtFiltroDescripcion->Text != '') {
                $Sql = $Sql . " AND  DescripcionProveedor LIKE '%" . $objVista->TxtFiltroDescripcion->Text . "%'";
            }

            if ($objVista->TxtFiltroDescripcionCte->Text != '') {
                $Sql = $Sql . " AND  DescripcionCliente LIKE '%" . $objVista->TxtFiltroDescripcionCte->Text . "%'";
            }

            if ($objVista->ChkLCDInactivos->Checked == true) {
                $Sql = $Sql . " AND  lista_costos_prov_det.Inactivo = 1";
            }

            if ($objVista->ChkAlternativa->Checked == true) {
                $Sql = $Sql . " AND  Alternativa = 1";
            }
            if ($objVista->ChkHabCotizar->Checked == true) {
                $Sql = $Sql . " AND  lista_costos_prov_det.HabCotizar = 1";
            }

            if ($objVista->ChkNoHabCotizar->Checked == true) {
                $Sql = $Sql . " AND  lista_costos_prov_det.HabCotizar = 0";
            }
            if ($objVista->ChkConDisponible->Checked == true) {
                $Sql = $Sql . " AND (SELECT SUM(Disponible) as Disponible FROM lotes LEFT JOIN bodegas ON bodegas.IdBodega = lotes.Bodega 
                           WHERE bodegas.SumaDisponible=1 AND lotes.Id_Item=lista_costos_prov_det.Id_Item) > 0";
            }

            if ($objVista->ChkContrato->Checked == true) {
                $Sql = $Sql . " AND  item.Contrato = 1";
            }

            if ($objVista->ChkEnviados->Checked == true) {
                $Sql = $Sql . " AND  (plantillas_det.EnlaceCot >0 or plantillas_det.IdConsolidadoDet>0)";
            }

            if ($objVista->ChkNoEnviados->Checked == true) {
                $Sql = $Sql . " AND  (plantillas_det.EnlaceCot <=0 or plantillas_det.EnlaceCot is null and plantillas_det.IdConsolidadoDet<=0)";
            }

            if ($objVista->ChkRevisados->Checked == true) {
                $Sql = $Sql . " AND  plantillas_det.Revisado >0";
            }
            if ($objVista->TxtIdTercero->Text != '') {
                $Sql = $Sql . " AND (ListaProv.IdTercero=" . $objVista->TxtIdTercero->Text . " or lista_costos_prov.IdTercero = " . $objVista->TxtIdTercero->Text . ")";
            }
            if ($objVista->TxtCodCliente->Text != '') {
                $Sql = $Sql . " AND  plantillas_det.CodCliente = '" . $objVista->TxtCodCliente->Text . "'";
            }
            if ($objVista->ChkNoRevisados->Checked == true) {
                $Sql = $Sql . " AND  (plantillas_det.Revisado is null or  plantillas_det.Revisado<=0)";
            }
            if ($objVista->TxtReferencia->Text != '') {
                $Sql = $Sql . " AND  lista_costos_prov_det.RefFabricante = '" . $objVista->TxtReferencia->Text . "'";
            }
            if ($objVista->CboUnidadesCliente->SelectedValue != '') {
                $Sql = $Sql . " AND  plantillas_det.UMCliente='" . $objVista->CboUnidadesCliente->SelectedValue . "'";
            } elseif ($objVista->getViewState('UmCliente') != '') {
                $Sql = $Sql . " AND  plantillas_det.UMCliente='" . $objVista->getViewState('UmCliente') . "'";
            }
            if ($objVista->ChkNoEnviadosLista->Checked == true) {
                $Sql = $Sql . " AND  plantillas_det.ComentariosCliente='no-insertados'";
            }
            if (isset($objVista->Request['IdDet'])) {
                $Sql = $Sql . " AND  plantillas_det.IdPlantillaDet=" . $objVista->Request['IdDet'];
            }
        } else {
            $Sql = $Sql . " AND  plantillas_det.ComentariosCliente='no-insertados'";
        }

        if ($SqlAdicion != '') {
            $Sql .= $SqlAdicion;
        }
        try {
            $EntroDuplicados = false;
            if ($objVista->ChkDuplicados->Checked) {
                $Sql .= " GROUP BY plantillas_det.IdTerceroCliente,Id_Item having( Repetidos > 1 ";
                if ($objVista->txtVarmenor->Text != '') {
                    $Sql = $Sql . " and Variacion <= " . $objVista->txtVarmenor->Text . "";
                    $EntroDuplicados = true;
                } else if ($objVista->txtVarmayor->Text != '') {
                    $Sql = $Sql . " and Variacion >= " . $objVista->txtVarmayor->Text . "";
                    $EntroDuplicados = true;
                }
                $Sql .= " )";
            } else {
                $Sql .= " GROUP BY plantillas_det.IdPlantillaDet";
            }
        } catch (Exception $e) {
            $Sql .= " GROUP BY plantillas_det.IdPlantillaDet";
        }

        if ($objVista->txtVarmenor->Text != '' && !$EntroDuplicados) {
            $Sql = $Sql . " HAVING (Variacion <= " . $objVista->txtVarmenor->Text . ")";
        } else if ($objVista->txtVarmayor->Text != '' && !$EntroDuplicados) {
            $Sql = $Sql . " HAVING (Variacion >= " . $objVista->txtVarmayor->Text . ")";
        }



        $Sql = $Sql . " ORDER BY Grupo,IdItemCliente,CodCliente";
        if ($OpNoEnviados == false) {
            $Op = $objVista->getViewState('Opcion');
            $objVista->TASql->Data = $Sql;
            $PlantillaDet = PlantillasDetRecord::finder('PlantillasDetExtRecord')->FindAllBySql($Sql);
            $objVista->DGPlantillasDet->DataSource = $PlantillaDet;
            $objVista->DGPlantillasDet->dataBind();
            return $Sql;
        } else {
            $PlantillaDet = PlantillasDetRecord::finder('PlantillasDetExtRecord')->FindAllBySql($Sql);
            return $PlantillaDet;
        }
    }

    public static function CargarDetallesPlantillaProveedor($Plantilla, $objVista) {
        $PlantillaDet = new PlantillasProveedoresDetRecord();
        $Sql = "SELECT plantillasProveedores_det.*,
                clasificaciones_riesgo.NmClasificacionRiesgo
                FROM plantillasProveedores_det
                LEFT JOIN clasificaciones_riesgo
                ON plantillasProveedores_det.IdClasificacionRiesgo = clasificaciones_riesgo.IdClasificacionRiesgo
                WHERE plantillasProveedores_det.IdPlantillaProv =" . $Plantilla . "";

        $objVista->TASql->Data = $Sql;
        $PlantillaDet = PlantillasProveedoresDetRecord::finder('PlantillasProveedoresDetExtRecord')->FindAllBySql($Sql);
        $objVista->DGPlantillasProvDet->DataSource = $PlantillaDet;
        $objVista->DGPlantillasProvDet->dataBind();
        return $Sql;
    }

    public function EliminarDetalleRequerimiento($IdMovDet) {
        $ReqDet = new RequerimientoDetRecord();
        $Sql = "select * from requerimientos_det where (IdOCGenerada = " . $IdMovDet . " or IdOCGenerada2 = " . $IdMovDet . " or IdOcGenerada3=" . $IdMovDet . ")";
        $ReqDet = RequerimientoDetRecord::finder()->FindBySql($Sql);
        if (count($ReqDet) > 0 && $ReqDet->IdOCGenerada == $IdMovDet) {
            $ReqDet->IdOCGenerada = null;
            $ReqDet->Autorizado = 0;
            $ReqDet->Estado = 'DIGITADO';
            $ReqDet->save();
        } else if (count($ReqDet) > 0 && $ReqDet->IdOCGenerada2 == $IdMovDet) {
            $ReqDet->IdOCGenerada2 = null;
            $ReqDet->Autorizado = 0;
            $ReqDet->Estado = 'DIGITADO';
            $ReqDet->save();
        } else if (count($ReqDet) > 0 && $ReqDet->IdOcGenerada3 == $IdMovDet) {
            $ReqDet->IdOcGenerada3 = null;
            $ReqDet->Autorizado = 0;
            $ReqDet->Estado = 'DIGITADO';
            $ReqDet->save();
        }

        $ReqDet = new RequerimientosForecastDetRecord();
        $Sql = "select * from requerimientos_forecast_det where IdOCGenerada = " . $IdMovDet;
        $ReqDet = RequerimientosForecastDetRecord::finder()->FindBySql($Sql);
        if (count($ReqDet) > 0) {
            $ReqDet->IdOCGenerada = null;
            $ReqDet->Estado = 'DIGITADO';
            $ReqDet->save();
        }
        return true;
    }

    public function CrearLogRequerimientos($IdReqDet = '', $IdReque = '', $IdAccion, $NuevoOc = '', $NuevoOcAnt = '', $Observaciones = '', $ObservacionesAnt = '', $Comentarios = '', $ObjVista) {
        try {

            $Log = new LogRequerimientosRecord();
            $Log->IdRequerimientoDet = $IdReqDet;
            $Log->IdRequerimiento = $IdReque;
            $Log->IdAccion = $IdAccion;
            $Log->NuevoOC = $NuevoOc;
            $Log->NuevoOCAnt = $NuevoOcAnt;
            $Log->Observaciones = $Observaciones;
            $Log->ObservacionesAnt = $ObservacionesAnt;
            $Log->Usuario = $ObjVista->User->Name;
            $Log->Fecha = date("Y-m-d H:i:s");
            $Log->Comentarios = $Comentarios;
            $Log->save();
            return true;
        } catch (Exeption $e) {
            funciones::Mensaje("Error " . $e, 2, $ObjVista);
        }
    }

    public function CrearLogRequerimientosForecast($IdReqDet = '', $IdReque = '', $IdAccion, $NuevoOc = '', $NuevoOcAnt = '', $Observaciones = '', $ObservacionesAnt = '', $Comentarios = '', $Objvista) {
        try {
            $Log = new LogRequerimientosForeCastRecord();
            $Log->IdRequerimientoDet = $IdReqDet;
            $Log->IdRequerimiento = $IdReque;
            $Log->IdAccion = $IdAccion;
            $Log->NuevoOC = $NuevoOc;
            $Log->NuevoOCAnt = $NuevoOcAnt;
            $Log->Observaciones = $Observaciones;
            $Log->ObservacionesAnt = $ObservacionesAnt;
            $Log->Usuario = $Objvista->User->Name;
            $Log->Fecha = date("Y-m-d H:i:s");
            $Log->Comentarios = $Comentarios;
            $Log->save();
            return true;
        } catch (Exeption $e) {
            funciones::Mensaje("Error " . $e, 2, $this);
        }
    }

    public function CargarDatagridRequerimientos($Objvista) {
        $RecCompras = new RequerimientoDetRecord();
        $Sql = "select  
                requerimientos_det.IdRequerimientoDet,
                requerimientos_det.IdRequerimiento,
                requerimientos_det.Id_Item,
                requerimientos_det.Proveedor,
                requerimientos_det.ClasificacionProvLC,
                requerimientos_det.Referencia,
                requerimientos_det.MesesInventario,
                requerimientos_det.UMC,
                requerimientos_det.FacUMC,
                requerimientos_det.CantOcPendPorLlegar,
                requerimientos_det.TCantPedCliente,
                requerimientos_det.UMM,
                requerimientos_det.TpCompra,
                requerimientos_det.TpReq,
                requerimientos_det.ComSugAjustada,
                requerimientos_det.NuevaOC,
                requerimientos_det.CostoUndUMM,
                requerimientos_det.SubTotal,
                requerimientos_det.Descuento,
                requerimientos_det.Autorizado,
                requerimientos_det.PromVtaMes,
                requerimientos_det.FrecuentaVtaMes,
                requerimientos_det.CategoriaP,
                requerimientos_det.HabCotizar,
                requerimientos_det.RequerimientoStock,
                if(requerimientos_det.TProvision is null,'0',requerimientos_det.TProvision) TProvision,
                requerimientos_det.PromedioDespachoItem,
                requerimientos_det.Estado,
                requerimientos_det.Enero,
                requerimientos_det.Febrero,
                requerimientos_det.Marzo,
                requerimientos_det.Abril,
                requerimientos_det.Mayo,
                requerimientos_det.Junio,
                requerimientos_det.Julio,
                requerimientos_det.Agosto,
                requerimientos_det.Septiembre,
                requerimientos_det.Octubre,
                requerimientos_det.Noviembre,
                requerimientos_det.Diciembre,
                requerimientos_det.Observaciones,
                requerimientos_det.Tpedido,
                requerimientos_det.DisponibleReq,
                requerimientos_det.IdOCGenerada,
                requerimientos_det.AplicaEscala,
                requerimientos_det.AplicaGrupo,
                requerimientos_det.IdListaCostosProvDetReq,
                requerimientos_det.TRemision,
                requerimientos_det.TReserva,
                requerimientos_det.OCPendientePorllegar,	
                requerimientos_det.TSolicitudesPendientes,
                requerimientos_det.EnContrato,
                requerimientos_det.PedidoT,
                requerimientos_det.NuevaOC2,
                requerimientos_det.NuevaOC3,
                requerimientos_det.IdOCGenerada2,
                requerimientos_det.IdOcGenerada3,
                terceros.NombreCorto as Cliente,
                requerimientos_det.ComentariosPedido,
                if(Tpedido<=0,'0',Tpedido) as Tpedido , if(proveedor.NombreCorto !='',proveedor.NombreCorto,'N/A') as NombreCorto,Descripcion,item.Disponible,item.Remisionada,if(requerimientos_det.HabCotizar =1,'SI','NO')  as HabCotizar,if(Autorizado is null,'',if(Autorizado = 1,'SI','NO')) AS Autorizado,if(terceros.IdTercero>0,terceros.IdTercero,'0')as IdTercero,if(proveedor.IdTercero>0,proveedor.IdTercero,'') as IdTerceroProv,
                if(Enero is not null,Enero,'') as Enero, if(Febrero is not null,Febrero,'') as Febrero, if(Marzo is not null,Marzo,'') as Marzo, if(Abril is not null,Abril,'') as Abril, if(Mayo is not null,Mayo,'') as Mayo, if(Junio is not null,Junio,'') as Junio, if(Julio is not null,Julio,'') as Julio, if(Agosto is not null,Agosto,'') as Agosto, if(Septiembre is not null,Septiembre,'') as Septiembre, if(Octubre is not null,Octubre,'') as Octubre, if(Noviembre is not null,Noviembre,'') as Noviembre, if(Diciembre is not null,Diciembre,'') as Diciembre,if(IdOCGenerada >0,IdOCGenerada,'') as IdOCGenerada,if(EnContrato = 1,'SI','NO') as IdContratoDet,sql_listas_especiales.ListaEspecial
                ,item.Descripcion as Detalle,Empaque,lista_costos_prov_det.Eleccion";
        if ($Objvista->ChkEscalas->Checked == true) {
            $Sql .= ",(select GROUP_CONCAT(IdEscala) from sql_escalas_Consulta where IdListaCostosDet = lista_costos_prov_det.IdListaCostosProvDet and FchHastaEsc >= CURDATE() and Autorizado = 1) as Escala";
        }
        $Sql .= " from requerimientos_det 
                LEFT JOIN item on item.Id_Item = requerimientos_det.Id_Item
                LEFT JOIN terceros on terceros.IdTercero = requerimientos_det.Cliente
                LEFT JOIN lista_costos_prov_det on lista_costos_prov_det.IdListaCostosProvDet = if(requerimientos_det.IdListaCostosProvDetReq>0,requerimientos_det.IdListaCostosProvDetReq,item.IdListaCostosDetItem)
                LEFT JOIN lista_costos_prov on lista_costos_prov.IdListaCostosProv = lista_costos_prov_det.IdListaCostosProv
                LEFT JOIN terceros  as proveedor on proveedor.IdTercero = lista_costos_prov.IdTercero
                LEFT JOIN marcas on marcas.IdMarca = lista_costos_prov_det.IdMarca
                LEFT JOIN sql_listas_especiales ON (sql_listas_especiales.Id_Item = item.Id_Item and sql_listas_especiales.IdTercero = terceros.IdTercero)
                where IdRequerimiento =  " . $Objvista->Request['IdRequerimiento'];

        if ($Objvista->TxtId_Item->Text != '') {
            $Row = explode(',', $Objvista->TxtId_Item->Text);
            if (count($Row) > 1) {
                for ($i = 0; $i < count($Row); $i++) {
                    if ($i == 0) {
                        if (strlen($Row[$i]) <= 5) {
                            $Sql = $Sql . " and (item.Id_Item =" . $Row[$i];
                        } else {
                            $i = $i - 1;
                        }
                    } else {
                        if (strlen($Row[$i]) <= 5) {
                            $Sql = $Sql . " OR item.Id_Item =" . $Row[$i];
                        }
                    }
                }
                $Sql .= ")";
            } else {
                $Sql = $Sql . " and item.Id_Item =" . $Objvista->TxtId_Item->Text;
            }
        }

        if ($Objvista->TxtReferenciaProv->Text != '') {
            $Sql = $Sql . " and lista_costos_prov_det.RefFabricante like'%" . $Objvista->TxtReferenciaProv->Text . "%'";
        }

        if ($Objvista->Cbo_Proveedores->SelectedValue != '') {
            $Sql = $Sql . " and requerimientos_det.Proveedor = " . $Objvista->Cbo_Proveedores->SelectedValue;
        }

        if ($Objvista->Cbo_Marca->SelectedValue != '') {
            $Sql = $Sql . " and marcas.IdMarca = " . $Objvista->Cbo_Marca->SelectedValue;
        }

        if ($Objvista->Cbo_Rotacion->SelectedValue != '') {
            $Sql = $Sql . " and requerimientos_det.CategoriaP = '" . $Objvista->Cbo_Rotacion->SelectedValue . "'";
        }

        if ($Objvista->TxtDescripcionRc->Text != '') {
            $Sql = $Sql . " and item.Descripcion LIKE '%" . $Objvista->TxtDescripcionRc->Text . "%'";
        }

        if ($Objvista->Cbo_TipoCompra->SelectedValue != '') {
            $Sql = $Sql . " and requerimientos_det.TpCompra = '" . $Objvista->Cbo_TipoCompra->SelectedValue . "'";
        }

        if ($Objvista->Cbo_HabCot->SelectedValue != '') {
            $Sql = $Sql . " and lista_costos_prov_det.HabCotizar = " . $Objvista->Cbo_HabCot->SelectedValue;
        }

        if ($Objvista->ChkPendiente->Checked == true && $Objvista->ChkReposicion->Checked == true) {
            $Sql = $Sql . " and (TpReq = 'Pend' or TpReq = 'Rep')";
        } else if ($Objvista->ChkReposicion->Checked == true) {
            $Sql = $Sql . " and (TpReq = 'Rep')";
        } else if ($Objvista->ChkPendiente->Checked == true) {
            $Sql = $Sql . " and (TpReq = 'Pend')";
        }

        if ($Objvista->Cbo_Autorizado->SelectedValue != '') {
            if ($Objvista->Cbo_Autorizado->SelectedValue == 'null') {
                $Sql = $Sql . " and (Autorizado is null)";
            } else {
                $Sql = $Sql . " and (Autorizado = " . $Objvista->Cbo_Autorizado->SelectedValue . ")";
            }
        }

        if ($Objvista->ChkMayor->Checked == true) {
            if ($Objvista->N1->Checked) {
                $Sql = $Sql . "  and NuevaOC >0";
            } else if ($Objvista->N2->Checked) {
                $Sql = $Sql . "  and NuevaOC2 >0";
            } else if ($Objvista->N3->Checked) {
                $Sql = $Sql . "  and NuevaOC3 >0";
            }
        }

        if ($Objvista->ChkMenor->Checked == true) {
            if ($Objvista->N1->Checked) {
                $Sql = $Sql . "  and (NuevaOC = 0 OR NuevaOC is null ) ";
            } else if ($Objvista->N2->Checked) {
                $Sql = $Sql . "  and (NuevaOC2 = 0 OR NuevaOC is null ) ";
            } else if ($Objvista->N3->Checked) {
                $Sql = $Sql . "  and (NuevaOC3 = 0 OR NuevaOC is null ) ";
            }
        }

        if ($Objvista->Cbo_Estado->SelectedValue != '') {
            $Sql = $Sql . " and (requerimientos_det.Estado = '" . $Objvista->Cbo_Estado->SelectedValue . "')";
        }

        if ($Objvista->Cbo_DivProv->SelectedValue != '') {
            $Sql = $Sql . " and (ClasificacionProvLC = '" . $Objvista->Cbo_DivProv->SelectedValue . "')";
        }

        if ($Objvista->OptSi->Checked == true && $Objvista->OptNo->Checked == true) {
            $Sql = $Sql . " and (requerimientos_det.RequerimientoStock >0 or requerimientos_det.RequerimientoStock <=0 or requerimientos_det.RequerimientoStock is null )";
        } else if ($Objvista->OptSi->Checked == false && $Objvista->OptNo->Checked == true) {
            $Sql = $Sql . " and requerimientos_det.RequerimientoStock <=0 ";
        } else if ($Objvista->OptSi->Checked == true) {
            $Sql = $Sql . " and requerimientos_det.RequerimientoStock > 0 ";
        }

        if ($Objvista->OptMayor->Checked == true) {
            $Sql = $Sql . " and requerimientos_det.MesesInventario>=" . $Objvista->TxtMesesInv->Text;
        }
        if ($Objvista->OptMenor->Checked == true) {
            $Sql = $Sql . " and requerimientos_det.MesesInventario<=" . $Objvista->TxtMesesInv->Text;
        }

        if ($Objvista->Cbo_Linea->SelectedValue != '') {
            $Sql = $Sql . " and item.IdLinea=" . $Objvista->Cbo_Linea->SelectedValue;
        }
        if ($Objvista->Cbo_Grupo->SelectedValue != '') {
            $Sql = $Sql . " and lista_costos_prov_det.IdGrupoLC=" . $Objvista->Cbo_Grupo->SelectedValue;
        }
        if ($Objvista->Cbo_Subgrupo->SelectedValue != '') {
            $Sql = $Sql . " and lista_costos_prov_det.IdSubGrupoLC=" . $Objvista->Cbo_Subgrupo->SelectedValue;
        }
        if ($Objvista->OptpSi->Checked == true) {
            $Sql = $Sql . " and requerimientos_det.TProvision >0";
        }


        if ($Objvista->ChkEscalas->Checked == true) {
            $Sql = $Sql . " GROUP BY requerimientos_det.IdRequerimientoDet having(Escala !='') ORDER BY Proveedor,CategoriaP,ClasificacionProvLC,Detalle";
        } else {
            $Sql = $Sql . " GROUP BY requerimientos_det.IdRequerimientoDet  ORDER BY Proveedor,CategoriaP,ClasificacionProvLC,Detalle";
        }
        $RecCompras = RequerimientoDetRecord::finder('RequerimientoDetExtRecord')->FindAllBySql($Sql);
        $Objvista->DGRequerimientosItem->DataSource = $RecCompras;
        $Objvista->DGRequerimientosItem->dataBind();
        $Objvista->LblNroRegistros->Text = count($RecCompras);
        $Objvista->TASql->Data = $Sql;
        //return $Sql;
    }

    /**
     * @Descripcion : Se crea funcion para establecer el CostoMvtoVig de cada detalle la primera vez que se liquide un movimiento
     * @param type $IdMovDet
     * @param type $Op
     * @return type Costo
     * FhCreacion : 28/02/2017
     */
    public function CostoActualVigDetalleMov($IdMovDet, $Op) {

        $MovDetActual = new MovimientosDetRecord();
        $MovDetActual = MovimientosDetRecord::finder()->FindByPk($IdMovDet);
        $Mov = MovimientosRecord::finder()->FindByPk($MovDetActual->IdMovimiento);
        if ($Mov->IdConcepto != 179) {
            $MovDetOrg = new MovimientosDetRecord();
            $MovDetOrg = MovimientosDetRecord::finder()->FindByPk($IdMovDet);
            if (count($MovDetOrg) > 0 && ($MovDetOrg->IdLista <= 0 || $MovDetOrg->IdLista == Null) && $MovDetOrg->Enlace != '') {
                $Enlace = $MovDetOrg->Enlace;
                $MovDetOrg = new MovimientosDetRecord();
                $MovDetOrg = MovimientosDetRecord::finder()->FindByPk($Enlace);
                if (count($MovDetOrg) > 0 && $MovDetOrg->IdLista <= 0 && $MovDetOrg->Enlace != '') {
                    $Enlace = $MovDetOrg->Enlace;
                    $MovDetOrg = new MovimientosDetRecord();
                    $MovDetOrg = MovimientosDetRecord::finder()->FindByPk($Enlace);
                    if (count($MovDetOrg) > 0 && $MovDetOrg->IdLista <= 0 && $MovDetOrg->Enlace != '') {
                        $Enlace = $MovDetOrg->Enlace;
                        $MovDetOrg = new MovimientosDetRecord();
                        $MovDetOrg = MovimientosDetRecord::finder()->FindByPk($Enlace);
                    }
                }
            }
            if (count($MovDetOrg) > 0 && $MovDetOrg->IdLista > 0) {

                //Valida Precios.
                if ($Op == 1) {

                    $LCDet = new ListaCostosProvDetRecord();
                    $Sql = "Select lista_costos_prov_det.PrecioGeneral,lista_costos_prov_det.Costo,IdListaDetReferencia,
                            lista_costos_prov_det.Id_Item,lista_costos_prov_det.CostoUMM,CostosCliente, lista_costos_prov_det.FhDesde,
                            lista_costos_prov_det.FhHasta  
                            FROM lista_precios_det
                            LEFT JOIN lista_costos_prov_det on lista_costos_prov_det.IdListaCostosProvDet = lista_precios_det.IdListaCostosDet
                            LEFT JOIN lista_costos_prov on lista_costos_prov.IdListaCostosProv = lista_costos_prov_det.IdListaCostosProv
                            where IdListaPreciosDet = " . $MovDetOrg->IdLista;
                    $LCDet = ListaCostosProvDetRecord::finder("ListaCostosProvDetExtRecord")->FindBySql($Sql);
                    if (count($LCDet) > 0) {
                        $ListaGen = ListaCostosProvDetRecord::DevEnlaceRaiz($MovDetOrg->IdLista);
                        $EscalaLista = $ListaGen ? ListaCostosProvDetEscalasRecord::finder()->FindAllBy_IdListaCostosDet($ListaGen) : null;
                        if ($ListaGen) {
                            if ($EscalaLista) {
                                $EscalaLista = array_filter($EscalaLista, function($e) use ($MovDetActual) {
                                    return $e->Inactivo == 0 && $e->Autorizado == 1 && $MovDetActual->FechaDet >= $e->FhDesde && $MovDetActual->FechaDet <= $e->FhHasta;
                                });
                                $EscalaLista = $EscalaLista ? reset($EscalaLista) : null;
                            }
                        }
                        if ($LCDet->CostosCliente == 1 && ($MovDetActual->FechaDet >= $LCDet->FhDesde && $MovDetActual->FechaDet <= $LCDet->FhHasta)) {
                            $MovDetActual->CostoVigEspecial = 1;
                            $MovDetActual->save();
                            return $LCDet->CostoUMM;
                        } else if ($ListaGen && $EscalaLista) {
                            return $EscalaLista->CostoUMM;
                        } else {
                            return $LCDet->CostoUMM;
                        }
                    } else {
                        return null;
                    }
                }
                //Valida Costos.
                if ($Op == 2) {
                    if (count($MovDetOrg) > 0 && $MovDetOrg->IdLista != '') {
                        $LCDet = new ListaCostosProvDetRecord();
                        $Sql = "Select lista_costos_prov_det.*,CostosCliente from lista_costos_prov_det "
                                . " LEFT JOIN lista_costos_prov on lista_costos_prov.IdListaCostosProv = lista_costos_prov_det.IdListaCostosProv"
                                . " where IdListaCostosProvDet = " . $MovDetOrg->IdLista;
                        $LCDet = ListaCostosProvDetRecord::finder("ListaCostosProvDetExtRecord")->FindBySql($Sql);

                        $ListaGen = ListaCostosProvDetRecord::DevEnlaceRaiz($MovDetOrg->IdLista);
                        $EscalaLista = $ListaGen ? ListaCostosProvDetEscalasRecord::finder()->FindAllBy_IdListaCostosDet($ListaGen) : null;
                        if ($ListaGen) {
                            if ($EscalaLista) {
                                $EscalaLista = array_filter($EscalaLista, function($e) use ($MovDetActual) {
                                    return $e->Inactivo == 0 && $e->Autorizado == 1 && $MovDetActual->FechaDet >= $e->FhDesde && $MovDetActual->FechaDet <= $e->FhHasta;
                                });
                                $EscalaLista = $EscalaLista ? reset($EscalaLista) : null;
                            }
                        }
                        if (count($LCDet) > 0 && $LCDet->IdListaDetReferencia > 0) {
                            $LCDetRef = new ListaCostosProvDetRecord();
                            $LCDetRef = ListaCostosProvDetRecord::finder()->with_ListaCostos()->FindByPk($LCDet->IdListaDetReferencia);
                            if ($LCDetRef->ListaCostos->CostosCliente == 1) {
                                $MovDetOrg->CostoVigEspecial = 1;
                                $MovDetOrg->save();
                            }
                            return $LCDetRef->CostoUMM;
                        } else if ($ListaGen && $EscalaLista) {
                            return $EscalaLista->CostoUMM;
                        } else if (count($LCDet) > 0) {
                            if ($LCDet->CostosCliente == 1) {
                                $MovDetOrg->CostoVigEspecial = 1;
                                $MovDetOrg->save();
                            }
                            return $LCDet->CostoUMM;
                        } else {
                            return null;
                        }
                    } else {
                        return null;
                    }
                }
            } else {
                return null;
            }
        }
    }

    /**
     * @Descripcion : Se crea funcion para eliminar una reserva automatica.
     * @param type $IdMovDetRes
     * @return true
     * FhActualizacion :01/0372017
     */
    public static function EliminarReservaAut($IdMovDetRes) {
        if ($IdMovDetRes != null) {
            $Reserva = new ReservasAutomaticasRecord();
            $Reserva = ReservasAutomaticasRecord::finder()->FindBy_and_IdMovimientoDetRes($IdMovDetRes);
            if (count($Reserva) > 0) {
                ReservasAutomaticasRecord::finder()->deleteByPk($Reserva->IdReserva);
                return true;
            } else {
                return true;
            }
        }
        return true;
    }

    /**
     * @Descripcion Retorna ; si lo encuentra en las 2 primeras lineas si no retorna  ,
     * @param type $filetemp
     * @return DatosArray
     * FhActualizacion 28/03/2017.
     */
    public function ValidarArchivoCVS($filetemp) {
        $fp = fopen($filetemp, "r");
        $i = 0;
        while ($i < 2) {
            $Dato = fgets($fp);
            $Row = strpos($Dato, ';');
            if ($Row !== false) {
                $Row = true;
                break;
            }
            $i++;
        }
        if ($Row == true) {
            return ';';
        } else {
            return ',';
        }
        fclose($fp);
    }

    /**
     * Descripcion: El metodo PertenePromocion() devuelve un true si el item pertenece a  la lista de promociones.
     * Param:$Id_Item.
     * Return: true o false.
     * FhActualizacion: 3/02/2016.
     * */
    public function PertenePromocion($Id_Item) {
        $Promocion = new PromocionesDetRecord();
        $strSql = "select * from promociones_det where Id_Item = " . $Id_Item;
        $Promocion = PromocionesDetRecord::finder()->FindBySql($strSql);
        if (count($Promocion) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function DetectarDispositivo() {
        $tablet_browser = 0;
        $mobile_browser = 0;
        $body_class = 'desktop';

        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
            $tablet_browser++;
            $body_class = "tablet";
        }

        if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
            $mobile_browser++;
            $body_class = "mobile";
        }

        if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/vnd.wap.xhtml+xml') > 0) or ( (isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])))) {
            $mobile_browser++;
            $body_class = "mobile";
        }

        $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
        $mobile_agents = array(
            'w3c ', 'acs-', 'alav', 'alca', 'amoi', 'audi', 'avan', 'benq', 'bird', 'blac',
            'blaz', 'brew', 'cell', 'cldc', 'cmd-', 'dang', 'doco', 'eric', 'hipt', 'inno',
            'ipaq', 'java', 'jigs', 'kddi', 'keji', 'leno', 'lg-c', 'lg-d', 'lg-g', 'lge-',
            'maui', 'maxo', 'midp', 'mits', 'mmef', 'mobi', 'mot-', 'moto', 'mwbp', 'nec-',
            'newt', 'noki', 'palm', 'pana', 'pant', 'phil', 'play', 'port', 'prox',
            'qwap', 'sage', 'sams', 'sany', 'sch-', 'sec-', 'send', 'seri', 'sgh-', 'shar',
            'sie-', 'siem', 'smal', 'smar', 'sony', 'sph-', 'symb', 't-mo', 'teli', 'tim-',
            'tosh', 'tsm-', 'upg1', 'upsi', 'vk-v', 'voda', 'wap-', 'wapa', 'wapi', 'wapp',
            'wapr', 'webc', 'winw', 'winw', 'xda ', 'xda-');

        if (in_array($mobile_ua, $mobile_agents)) {
            $mobile_browser++;
        }

        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'opera mini') > 0) {
            $mobile_browser++;
            //Check for tablets on opera mini alternative headers
            $stock_ua = strtolower(isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA']) ? $_SERVER['HTTP_X_OPERAMINI_PHONE_UA'] : (isset($_SERVER['HTTP_DEVICE_STOCK_UA']) ? $_SERVER['HTTP_DEVICE_STOCK_UA'] : ''));
            if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $stock_ua)) {
                $tablet_browser++;
            }
        }
        if ($tablet_browser > 0) {
            // Si es tablet has lo que necesites
            return '1';
        } else if ($mobile_browser > 0) {
            // Si es dispositivo mobil has lo que necesites
            return '1';
        } else {
            // Si es ordenador de escritorio has lo que necesites
            return '0';
        }
    }

    /**
     * Descripcion: el metodo guarda el costo de una escala en el movimiento det o de un requerimiento o cotizacion.
     * @param type $IdListaCostosDet
     * @param type $IdMovimientoDet
     */
    public static function ValidarCostosEscala($Dato1, $Dato2, $Opcion, $OpCot = '') {
        switch ($Opcion) {

            //Valida las escalas de un movmiento.
            case 1;
                if (isset($Dato1) && $Dato1 != '') {
                    $BoolVal = false;
                    $Escalas = new ListaCostosProvDetEscalasRecord();
                    $Sql = "select * from lista_costos_prov_det_escalas where IdListaCostosDet = $Dato1 and (OpcionDoc = 1 or OpcionDoc = 3)and Autorizado=1 ORDER BY CostoUMM";
                    $Escalas = ListaCostosProvDetEscalasRecord::finder()->FindAllBySql($Sql);
                    $ListaDet = ListaCostosProvDetRecord::finder()->FindByPk($Dato1);
                    if (count($Escalas) > 0) {
                        $MovDet = new MovimientosDetRecord();
                        $MovDet = MovimientosDetRecord::finder()->findByPk($Dato2);
                        if ($MovDet->TpDocumento == 3) {
                            foreach ($Escalas as $Escalas) {
                                if (date('Y-m-d', strtotime($Escalas->FhHasta)) >= date("Y-m-d") && ($Escalas->OpcionDoc == 1 || $Escalas->OpcionDoc == 3)) {
                                    if ($MovDet->Cantidad >= $Escalas->Cantidad && $MovDet->Cantidad <= $Escalas->Cantidad2) {
                                        $MovDet->Costo = $Escalas->CostoUMM;
                                        $MovDet->save();
                                        $BoolVal = true;
                                        break;
                                        return $BoolVal;
                                    } else {
                                        $MovDet->Costo = $ListaDet->CostoUMM;
                                        $MovDet->save();
                                    }
                                }
                            }
                            return $BoolVal;
                        }
                    }
                }
                break;

            //Validalas escalas de un requerimiento.
            case 2;
                $BoolVal = false;
                $Item = new ItemRecord();
                $Item = ItemRecord::finder()->FindByPK($Dato1);
                if (count($Item) > 0 && $Item->IdListaCostosDetItem > 0) {
                    $Escalas = new ListaCostosProvDetEscalasRecord();
                    $Sql = "select * from lista_costos_prov_det_escalas where IdListaCostosDet = $Item->IdListaCostosDetItem and (OpcionDoc = 1 or OpcionDoc = 3) and Autorizado=1 ORDER BY CostoUMM";
                    $Escalas = ListaCostosProvDetEscalasRecord::finder()->FindAllBySql($Sql);
                    $ListaDet = ListaCostosProvDetRecord::finder()->FindByPk($Item->IdListaCostosDetItem);
                    if (count($Escalas) > 0) {
                        $ReqDet = new RequerimientoDetRecord();
                        $ReqDet = RequerimientoDetRecord::finder()->findByPk($Dato2);
                        foreach ($Escalas as $Escalas) {
                            if (date('Y-m-d', strtotime($Escalas->FhHasta)) >= date("Y-m-d") && ($Escalas->OpcionDoc == 1 || $Escalas->OpcionDoc == 3)) {
                                if ($ReqDet->NuevaOC >= $Escalas->Cantidad && $ReqDet->NuevaOC <= $Escalas->Cantidad2) {
                                    $ReqDet->CostoUndUMM = $Escalas->CostoUMM;
                                    $ReqDet->SubTotal = $ReqDet->NuevaOC * $ReqDet->CostoUndUMM;
                                    $ReqDet->AplicaEscala = 1;
                                    $ReqDet->AplicaGrupo = 0;
                                    $ReqDet->save();
                                    $BoolVal = true;
                                    break;
                                    return $BoolVal;
                                } else {
                                    $ReqDet->CostoUndUMM = $ListaDet->CostoUMM;
                                    $ReqDet->SubTotal = $ReqDet->NuevaOC * $ReqDet->CostoUndUMM;
                                    $ReqDet->AplicaEscala = 0;
                                    $ReqDet->AplicaGrupo = 0;
                                    $ReqDet->save();
                                }
                            }
                        }
                        return $BoolVal;
                    }
                }
                break;
        }
    }

    /**
     * @Descripcion : El metodo valida de los productos de una oc o un requerimiento cuales productos son de un grupo para validar si la cantidad aplica para la escala que tiene el grupo
     * @param type $Dato1
     * @param type $Op
     * @param type $IdItem
     */
    public static function ValidarGruposEscalas($Dato1, $Op, $IdItem = '') {
        if ($Op == 1) {
            $Sql = "select grupos_escalas.* from grupos_escalas
                    LEFT JOIN  movimientos_det on movimientos_det.Id_Item = grupos_escalas.IdItem
                    where IdMovimiento = $Dato1 and Id_Item = $IdItem GROUP BY IdEscalaDet";
            $DetaLleXGrupos = new GruposEscalasRecord();
            $DetaLleXGrupos = GruposEscalasRecord::finder()->FindAllBySql($Sql);

            $FhPrincipal = "";
            foreach ($DetaLleXGrupos as $Row) {
                if ($Row->Principal == 1) {
                    $EscalaFh = ListaCostosProvDetEscalasRecord::finder()->FindByPk($Row->IdEscalaDet);
                    $FhPrincipal = $EscalaFh->FhHasta;
                }
            }
            foreach ($DetaLleXGrupos as $ArDet) {
                $Escala = new ListaCostosProvDetEscalasRecord();
                $Escala = ListaCostosProvDetEscalasRecord::finder()->FindByPk($ArDet->IdEscalaDet);
                $CantIniEscala = $Escala->Cantidad;
                $CantFinEscala = $Escala->Cantidad2;
                $SqlDet = " select movimientos_det.*,IdEscalaDet from movimientos_det
                            LEFT JOIN grupos_escalas on grupos_escalas.IdItem = movimientos_det.Id_Item
                            where IdMovimiento = " . $Dato1 . " and IdEscalaDet =" . $ArDet->IdEscalaDet . " ORDER BY Cantidad DESC";
                $MovDetalles = new MovimientosDetRecord();
                $MovDetalles = MovimientosDetRecord::finder('MovimientosDetExtRecord')->FindAllBySql($SqlDet);
                $Cont = 0;
                $strMovDet = '';
                foreach ($MovDetalles as $MovDetalles) {
                    $ListaVal = ListaCostosProvDetRecord::finder()->FindByPk($MovDetalles->IdLista);
                    $Cont = $Cont + $MovDetalles->Cantidad;
                    if ($Cont >= $CantFinEscala || $Cont >= $CantIniEscala) {
                        $strMovDet = $strMovDet . "-" . $MovDetalles->IdMovimientoDet;
                    } else {
                        $MovDetalles->Costo = $ListaVal->CostoUMM;
                    }
                }
                if ($strMovDet != '') {
                    $Row = explode("-", $strMovDet);
                    foreach ($Row as $MovAfec) {
                        if (is_numeric($MovAfec)) {
                            $MovDet = MovimientosDetRecord::finder()->FindByPk($MovAfec);
                            $ListaDet = ListaCostosProvDetRecord::finder()->FindByPk($MovDet->IdLista);
                            $MovDet = new MovimientosDetRecord();
                            $MovDet = MovimientosDetRecord::finder()->FindByPk($MovAfec);
                            if ($Cont >= $Escala->Cantidad && $MovDet->Cantidad > 0) {
                                $FhPrincipal = date('Y-m-d', strtotime($Escala->FhHasta));
                                if ($FhPrincipal >= date("Y-m-d") && ($Escala->OpcionDoc == 1 || $Escala->OpcionDoc == 3)) {
                                    $MovDet->Costo = $Escala->CostoUMM;
                                    $MovDet->save();
                                } else {
                                    $MovDet->Costo = $ListaDet->CostoUMM;
                                    $MovDet->save();
                                }
                            } else {
                                $MovDet->Costo = $ListaDet->CostoUMM;
                                $MovDet->save();
                            }
                        }
                    }
                }
            }
        } else if ($Op == 2) {
            $Sql = "SELECT grupos_escalas.*,IdRequerimiento,IdRequerimientoDet from grupos_escalas 
                    LEFT JOIN requerimientos_det on requerimientos_det.Id_Item = grupos_escalas.IdItem
                    LEFT JOIN lista_costos_prov_det_escalas on lista_costos_prov_det_escalas.IdEscalaDet = grupos_escalas.IdEscalaDet
                    where IdRequerimiento = " . $Dato1 . " and lista_costos_prov_det_escalas.Inactivo=0 and  lista_costos_prov_det_escalas.Autorizado =1  and lista_costos_prov_det_escalas.FhHasta>=CURDATE()  and requerimientos_det.NuevaOC>0  group by IdEscalaDet";
            $DetaLleXGrupos = new GruposEscalasRecord();
            $DetaLleXGrupos = GruposEscalasRecord::finder('GruposEscalasExtRecord')->FindAllBySql($Sql);

            foreach ($DetaLleXGrupos as $DetReq) {
                $Item = new ItemRecord();
                $Item = ItemRecord::finder()->FindByPk($DetReq->IdItem);
                $Lista = ListaCostosProvDetRecord::finder()->FindByPk($Item->IdListaCostosDetItem);
                $Escala = new ListaCostosProvDetEscalasRecord();
                $Escala = ListaCostosProvDetEscalasRecord::finder()->FindByPk($DetReq->IdEscalaDet);
                $CantIniEscala = $Escala->Cantidad;
                $CantFinEscala = $Escala->Cantidad2;
                $SqlDet = "SELECT requerimientos_det.* from grupos_escalas 
                            LEFT JOIN requerimientos_det on requerimientos_det.Id_Item = grupos_escalas.IdItem
                            LEFT JOIN lista_costos_prov_det_escalas on lista_costos_prov_det_escalas.IdEscalaDet = grupos_escalas.IdEscalaDet
                            where grupos_escalas.IdEscalaDet =" . $DetReq->IdEscalaDet . " and requerimientos_det.IdRequerimiento=" . $Dato1 . " and (OpcionDoc = 1 or OpcionDoc = 3) and lista_costos_prov_det_escalas.Autorizado=1 and AplicaEscala =0 ";
                $DetallesReq = new RequerimientoDetRecord();
                $DetallesReq = RequerimientoDetRecord::finder()->FindAllBySql($SqlDet);
                if (count($DetallesReq) > 0) {
                    $arDetalles = '';
                    $Cont = 0;
                    foreach ($DetallesReq as $Row) {
                        $Cont = $Cont + $Row->NuevaOC;
                        if ($Cont <= $CantFinEscala) {
                            $arDetalles = $arDetalles . "-" . $Row->IdRequerimientoDet;
                        } else {
                            break;
                        }
                    }
                }
                if (isset($Row) && $Row != '') {
                    $Row = explode("-", $arDetalles);
                    foreach ($Row as $ReqDet) {
                        if (is_numeric($ReqDet)) {
                            $Detalle = new RequerimientoDetRecord();
                            $Detalle = RequerimientoDetRecord::finder()->FindByPk($ReqDet);
                            if ($Cont >= $CantFinEscala || $Cont >= $CantIniEscala) {
                                if (date('Y-m-d', strtotime($Escala->FhHasta)) >= date("Y-m-d") && ($Escala->OpcionDoc == 1 || $Escala->OpcionDoc == 3) && $Detalle->AplicaGrupo != 1) {
                                    $Detalle->CostoUndUMM = $Escala->CostoUMM;
                                    $Detalle->SubTotal = $Detalle->NuevaOC * $Detalle->CostoUndUMM;
                                    $Detalle->AplicaGrupo = 1;
                                    $Detalle->AplicaEscala = 0;
                                    $Detalle->save();
                                } else if (count($Lista) > 0) {
                                    $Detalle->CostoUndUMM = $Lista->CostoUMM;
                                    $Detalle->SubTotal = $Detalle->NuevaOC * $Detalle->CostoUndUMM;
                                    $Detalle->AplicaGrupo = 0;
                                    $Detalle->AplicaEscala = 0;
                                    $Detalle->save();
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public static function ValidarEscalasMov($IdMov) {
        $Sql = "select * from  movimientos_det where IdMovimiento = " . $IdMov;
        $Detalles = new MovimientosDetRecord();
        $Detalles = MovimientosDetRecord::finder()->FindAllBySql($Sql);
        if (count($Detalles) > 0) {
            foreach ($Detalles as $Detalles) {
                if (funciones::ValidarCostosEscala($Detalles->IdLista, $Detalles->IdMovimientoDet, 1) == false) {
                    funciones::ValidarGruposEscalas($IdMov, 1, $Detalles->Id_Item);
                }
            }
        }
    }

    public static function DevListaPreciosTercero($IdTercero, $idDireccion) {
        $Sql = "SELECT DISTINCt(lista_precios.IdListaPrecios) AS IdListaPrecios
                       FROM direcciones
                       LEFT JOIN lista_precios ON direcciones.IdListaPreciosDireccion = lista_precios.IdListaPrecios
                       WHERE lista_precios.Inactiva = 0 AND direcciones.IdTercero =" . $IdTercero . " and IdDireccion =" . $idDireccion . " ";
        $arListasPrecios = ListaPreciosRecord::finder()->FindBySql($Sql);
        $idListaPrecios = $arListasPrecios->IdListaPrecios;
        return $idListaPrecios;
    }

    /**
     * @Desc Valida que los items que van para factura no tengan precio vencido y que para confirmar tampoco.
     * @Op  Si es 1 para pasar a prefactura, 2 Si es para imprimir con items vencidos y 3 si es para confirmar items vencidos, si es 4 es generado desde factura.
     */
    public static function ValidarItemsVencidos($Op = 1, $ObjVista) {
        if ($Op == 4) {
            $NumReg = $ObjVista->DG_Items->ItemCount;
            $Items = '';
            $Cont = '';
            for ($i = 0; $i < $NumReg; $i++) {
                $Item = $ObjVista->DG_Items->Items[$i];
                if ($Item->ClmSeleccionar->Check->Checked == true) {
                    $MovDet = new MovimientosDetRecord();
                    $MovDet = MovimientosDetRecord::finder()->FindByPk($ObjVista->DG_Items->Items[$i]->ClmIdMovDet->Text);
                    $ItemRecord = new ItemRecord();
                    $ItemRecord = ItemRecord::finder()->FindByPk($MovDet->Id_Item);
                    $intDias = ListaPreciosDetRecord::DevVigenciaDetalle($MovDet->IdLista);
                }
                if (isset($intDias) && $ItemRecord->NoValidarVigenciaPrecio == 0) {
                    if ($intDias < 0 && $Op != 3 || $intDias < 0 && ($Op == 3 && (count($itemI) <= 0) )) {
                        if (!isset($ContratosDet) || (isset($ContratosDet) && count($ContratosDet) <= 0)) {//Validamos que no pertenezca a un contrato de cliente
                            $Items .= $MovDet->Id_Item . ' -';
                        }
                    }
                }
            }
        } else {
            $NumReg = $ObjVista->DGMovDet->ItemCount;
            $Items = '';
            $Cont = '';
            if ($Op == 3) {
                $Contratos = ContratosRecord::DevContratosXTercero($ObjVista->LblIdTercero->Text);
            }
            for ($i = 0; $i < $NumReg; $i++) {
                $Item = $ObjVista->DGMovDet->Items[$i];
                if ($Item->ClmSeleccionar->Check->Checked == true || $Op == 2) {
                    $MovDet = new MovimientosDetRecord();
                    $MovDet = MovimientosDetRecord::finder()->FindByPk($Item->ClmIdMovDet->LnkIdMovDet->Text);
                    $ItemRecord = new ItemRecord();
                    $ItemRecord = ItemRecord::finder()->FindByPk($MovDet->Id_Item);
                    $intDias = ListaPreciosDetRecord::DevVigenciaDetalle($MovDet->IdLista);
                    if ($Op == 3) {
                        //Buscamos si el item esta en la lista de abbot y se esta confirmando.
                        $itemI = new ListaCostosProvDetRecord();
                        $Sql = "select * from lista_costos_prov_det where IdlistaCostosProv = 4 and Id_Item =" . $MovDet->Id_Item . " and Inactivo =0";
                        $itemI = ListaCostosProvDetRecord::finder()->FindBySql($Sql);

                        //Buscamos si existe en un contrato del cliente y esta confirmando.

                        if (count($Contratos) > 0) {
                            $ContratosDet = new ContratosDetRecord();
                            $Sql = "select * from contratos_det where 1 ";
                            $Count = 0;
                            foreach ($Contratos as $Row) {
                                if ($Cont == 0) {
                                    $Sql .= " and (IdContrato = " . $Row->IdContrato;
                                } else {
                                    $Sql .= " or IdContrato =" . $Row->IdContrato;
                                }
                                $Cont++;
                            }
                            $Cont = 0;
                            $Sql .= " ) and Id_Item = " . $MovDet->Id_Item;
                            $ContratosDet = ContratosDetRecord::finder()->FindAllBySql($Sql);
                        }
                    }
                }
                if (isset($intDias) && $ItemRecord->NoValidarVigenciaPrecio == 0) {
                    if ($intDias < 0 && $Op != 3 || $intDias < 0 && ($Op == 3 && (count($itemI) <= 0) )) {
                        if (!isset($ContratosDet) || (isset($ContratosDet) && count($ContratosDet) <= 0)) {//Validamos que no pertenezca a un contrato de cliente
                            $Items .= $MovDet->Id_Item . ' -';
                        }
                    }
                }
            }
            return $Items;
        }
        return $Items;
    }

    /**
     * 
     * @param type $IdContrato
     * @param type $IdAccion
     * @param type $Comentario
     * @param type $Usuario
     * FhActializacion 11/09/17
     */
    public static function CrearLogContratos($IdContrato, $IdAccion, $Comentario, $Usuario) {
        $Log = new LogContratosRecord();
        $Log->Fecha = date("Y-m-d H:i:s");
        $Log->IdAccion = $IdAccion;
        $Log->IdContrato = $IdContrato;
        $Log->Usuario = $Usuario;
        $Log->Comentarios = $Comentario;
        $Log->save();
    }

    /**
     * Convierte uan cadena de texto en un array para extraer los numeros que esta contenga.
     * @param type $String
     * @return string
     * FhActualizacion: 12/09/17.
     */
    public static function ExtraerNumerosString($String) {
        $Dato = str_split($String);
        $Resultado = "";
        foreach ($Dato as $Row) {
            if (is_numeric($Row)) {
                $Resultado = $Resultado . $Row;
            }
        }
        return $Resultado;
    }

    /**
     * Devuelve un array con las columnas y datos que contienen las columnas enviandole Tabla = PruebaRecord NmTabla = prueba Criterio = a consulta adicional es opcional por ejemplo cuando es un detalle de un mov se debe agregar en el activerecord el objeto COLUMN_NAME.
     * @param type $Tabla
     * @param type $NmTabla
     * @param type $Criterio
     * @return Datos
     * FhActualizacion = 13/09/17
     */
    public static function DatosActualesTabla($Tabla, $NmTabla, $Criterio) {
        //Columnas de la tabla
        $Columnas = new $Tabla();
        $Sql = "select COLUMN_NAME from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME  = '" . $NmTabla . "'";
        $Columnas = $Tabla::finder()->FindAllBySql($Sql);

        //Datos de la tabla
        $DatosColumnas = new $Tabla();
        $strSql = "select * from $NmTabla" . " where 1 " . $Criterio;
        $DatosColumnas = $Tabla::finder()->FindBySql($strSql);
        foreach ($Columnas as $Row) {
            $NmColumna = $Row->COLUMN_NAME;
            $Datos[] = (array(
                $NmColumna => $DatosColumnas->$NmColumna,
            ));
        }
        if (count($Datos)) {
            return $Datos;
        }
    }

    /**
     * Genera una reserva sin tener que ir a seleccionarla solamente con que tenga disponible, de lo contrario crea una reserva por oc.
     * @param type $IdMovDet
     * @param type $IdMov
     * @param type $Item
     * @param type $Cantidad
     * @param type $ObjVista
     * @return boolean
     * FhActualización:21/09/2017
     */
    public static function Reservar($IdMovDet, $IdMov, $Item, $Cantidad, $ObjVista) {
        try {
            $GeneraRes = false;
            $ItemR = new ItemRecord();
            $ItemR = ItemRecord::finder()->FindByPk($Item);
            $MovDet = new MovimientosDetRecord();
            $MovDet = MovimientosDetRecord::finder()->FindByPk($IdMovDet);
            //Validamos que el lote tenga disponible en bodegas disponibles.
            $Lote = new LotesRecord();
            $Sql = "select lotes.* from lotes 
                 LEFT JOIN item on item.Id_Item = lotes.Id_Item
                 LEFT JOIN bodegas on bodegas.IdBodega = lotes.Bodega
                 where item.Id_Item = " . $Item . " and (bodegas.IdBodega =1 or bodegas.IdBodega = 6 or bodegas.IdBodega = 20 or bodegas.IdBodega =8) and lotes.Disponible >0 ORDER BY FhVencimiento ASC";
            $Lote = LotesRecord::finder()->FindAllBySql($Sql);
            $DispLote = 0;
            foreach ($Lote as $Disp) {
                $DispLote = $DispLote + $Disp->Disponible;
            }
            if ($DispLote > 0) {
                if ($DispLote >= $Cantidad && count($Lote) == 1 || $DispLote <= $Cantidad && count($Lote) == 1) {
                    if ($DispLote > $Cantidad) {
                        $Cantidad = $Cantidad;
                    } else {
                        $Cantidad = $DispLote;
                    }
                    $NuevaRes = new ReservasRecord();
                    $NuevaRes->IdMovimientoDetRes = $IdMovDet;
                    $NuevaRes->CantidadRes = $Cantidad;
                    $NuevaRes->Id_ItemRes = $Item;
                    $NuevaRes->BodegaRes = $Lote[0]->Bodega;
                    $NuevaRes->LoteRes = $Lote[0]->Lote;
                    $NuevaRes->UsuarioRes = $ObjVista->User->Name;
                    if ($NuevaRes->save()) {
                        $lotes = LotesRecord::finder()->findByPk($NuevaRes->Id_ItemRes, $NuevaRes->LoteRes, $NuevaRes->BodegaRes);
                        $lotes->Reserva = $lotes->Reserva + $Cantidad;
                        $lotes->save();
                        $GeneraRes = true;
                        $MovDet->Cantidad2 = $MovDet->Cantidad2 + $NuevaRes->CantidadRes;
                        $MovDet->Confirmado = 1;
                        $MovDet->save();
                    }
                } else {
                    foreach ($Lote as $LoteRes) {
                        if ($Cantidad > 0) {
                            if ($LoteRes->Disponible <= $Cantidad) {
                                $NuevaRes = new ReservasRecord();
                                $NuevaRes->IdMovimientoDetRes = $IdMovDet;
                                $NuevaRes->CantidadRes = $LoteRes->Disponible;
                                $NuevaRes->Id_ItemRes = $Item;
                                $NuevaRes->BodegaRes = $LoteRes->Bodega;
                                $NuevaRes->LoteRes = $LoteRes->Lote;
                                $NuevaRes->UsuarioRes = $ObjVista->User->Name;
                                if ($NuevaRes->save()) {
                                    $GeneraRes = true;
                                    $lotes = LotesRecord::finder()->findByPk($NuevaRes->Id_ItemRes, $NuevaRes->LoteRes, $NuevaRes->BodegaRes);
                                    $lotes->Reserva = $lotes->Reserva + $NuevaRes->CantidadRes;
                                    $lotes->save();
                                    $MovDet->Cantidad2 = $MovDet->Cantidad2 + $NuevaRes->CantidadRes;
                                    $MovDet->Confirmado = 1;
                                    $MovDet->save();
                                    $Cantidad = $Cantidad - $LoteRes->Disponible;
                                }
                            } else {
                                $NuevaRes = new ReservasRecord();
                                $NuevaRes->IdMovimientoDetRes = $IdMovDet;
                                $NuevaRes->CantidadRes = $Cantidad;
                                $NuevaRes->Id_ItemRes = $Item;
                                $NuevaRes->BodegaRes = $LoteRes->Bodega;
                                $NuevaRes->LoteRes = $LoteRes->Lote;
                                $NuevaRes->UsuarioRes = $ObjVista->User->Name;
                                if ($NuevaRes->save()) {
                                    $GeneraRes = true;
                                    $lotes = LotesRecord::finder()->findByPk($NuevaRes->Id_ItemRes, $NuevaRes->LoteRes, $NuevaRes->BodegaRes);
                                    $lotes->Reserva = $lotes->Reserva + $Cantidad;
                                    $lotes->save();
                                    $MovDet->Cantidad2 = $MovDet->Cantidad2 + $NuevaRes->CantidadRes;
                                    $MovDet->Confirmado = 1;
                                    $MovDet->save();
                                    $Cantidad = $Cantidad - $Cantidad;
                                }
                            }
                        }
                    }
                }
            } else if ($DispLote <= 0) {
                $MovDet->Confirmado = 1;
                if ($MovDet->save()) {
                    $ResOC = new ReservasAutomaticasRecord();
                    $ResOC->Fecha = date('Y-m-d H:i:s');
                    $ResOC->Cantidad = $Cantidad;
                    $ResOC->Usuario = $ObjVista->User->Name;
                    $ResOC->IdMovimientoDetRes = $MovDet->IdMovimientoDet;
                    $ResOC->save();
                    $GeneraRes = true;
                }
            }
            return $GeneraRes;
        } catch (Exception $e) {
            funciones::Mensaje("Ocurrio un error al generar las reservas automaticas", 2, $ObjVista);
        }
    }

    /**
     * @param type $IdMovDet
     * @param type $ObjVista
     * @param type $ValidaEdit
     * @param type $Aut
     * @param type $DesAut
     * @return boolean
     */
    public static function AcualizarEstadoDetMov($IdMovDet, $ObjVista, $ValidaEdit = true, $Aut = false, $DesAut = false) {
        if (funciones::ValidarGeneracionRequerimiento($IdMovDet, true) == false) {
            $Op = 0;
            $Estado = "";
            $Accion;
            if ($ValidaEdit == true) {
                if ($ObjVista->chkAut->Checked == true) {
                    $Op = 1;
                    $Estado = "AUTORIZADO";
                    $Accion = 1;
                } else if ($ObjVista->chkDesAut->Checked == true) {
                    $Op = 2;
                    $Estado = "DIGITADO";
                    $Accion = -1;
                }
            } else if ($ValidaEdit == false) {
                if ($Aut == true) {
                    $Op = 1;
                    $Estado = "AUTORIZADO";
                    $Accion = 1;
                } else if ($DesAut == true) {
                    $Op = 2;
                    $Estado = "DIGITADO";
                    $Accion = -1;
                }
            }

            if ($Op != 0) {
                $strIdMovDet = "";
                $MovDetDatos = new MovimientosDetRecord();
                $MovDetDatos = MovimientosDetRecord::finder()->with_MovDet()->FindByPk($IdMovDet);
                if ($Op == 1 && !funciones::ValidarLotesItems($MovDetDatos, $ObjVista)) {
                    return false;
                }
                $Mov = new MovimientosRecord();
                $Mov = MovimientosRecord::finder()->FindByPk($MovDetDatos->IdMovimiento);
                if (funciones::ComprobarPermiso($ObjVista->LblIdDocumento->Text, 5, $ObjVista) == true) {
                    if ($Estado == "AUTORIZADO" && $MovDetDatos->Estado != "AUTORIZADO" || $Estado == "DIGITADO" && $MovDetDatos->Estado != "DIGITADO") {
                        $Vali = funciones::ActualizarEstadoDetAll($IdMovDet, $Estado, $Accion, $MovDetDatos->IdDocumento, $ObjVista, true, false);
                        $ValiReg = funciones::RegistrarAfectada($IdMovDet, $Op, $ObjVista, true, true, false);

                        if ($Mov->Estado != "ANULADA" && $Mov->Estado != "CERRADA" && ($MovDetDatos->Estado == "DIGITADO" || $MovDetDatos->Estado == "AUTORIZADO" )) {
                            if ($Vali == true && $ValiReg == true) {
                                if ($Op == 1) {
                                    $Log = new LogRecord();
                                    $Log->Fecha = date('Y-m-d H:i:s');
                                    $Log->IdAccion = 2;
                                    $Log->Usuario = $ObjVista->User->Name;
                                    $Log->Id_Item = $MovDetDatos->Id_Item;
                                    $Log->IdMovimientoDet = $MovDetDatos->IdMovimientoDet;
                                    $Log->IdMovimiento = $MovDetDatos->IdMovimiento;
                                    $Log->Comentarios = "Autorizar Items.";
                                    $Log->save();
                                    return true;
                                } else {
                                    $Log = new LogRecord();
                                    $Log->Fecha = date('Y-m-d H:i:s');
                                    $Log->IdAccion = 3;
                                    $Log->Usuario = $ObjVista->User->Name;
                                    $Log->Id_Item = $MovDetDatos->Id_Item;
                                    $Log->IdMovimientoDet = $MovDetDatos->IdMovimientoDet;
                                    $Log->IdMovimiento = $MovDetDatos->IdMovimiento;
                                    $Log->Comentarios = "Des-Autorizar Items.";
                                    $Log->save();
                                    return true;
                                }
                            } else {
                                //Si ocurrio en error en alguna de las 2 validaciones devolvemos la transaccion ejecutada
                                if ($Vali == true && $ValiReg == false) {
                                    if ($Accion == 1) {
                                        funciones::ActualizarEstadoDetAll($IdMovDet, "DIGITADO", -1, $MovDetDatos->IdDocumento, $ObjVista, true, false);
                                    } else {
                                        funciones::ActualizarEstadoDetAll($IdMovDet, "AUTORIZADO", 1, $MovDetDatos->IdDocumento, $ObjVista, true, false);
                                    }
                                } else if ($Vali == false && $ValiReg == true) {
                                    if ($Op == 1) {
                                        funciones::RegistrarAfectada($IdMovDet, 2, $ObjVista, true, true, false);
                                    } else {
                                        funciones::RegistrarAfectada($IdMovDet, 1, $ObjVista, true, true, false);
                                    }
                                }
                                return false;
                            }
                        }
                    } else {
                        funciones::Mensaje("El detalle ya tiene el mismo estado que le estas actualizando.", 2, $ObjVista);
                        return false;
                    }
                } else {
                    funciones::Mensaje("No tienes permisos para autorizar o desautorizar productos.", 2, $ObjVista);
                    return false;
                }
            } else {
                return true;
            }
        } else {
            funciones::Mensaje("No puedes cambiar el estado del producto  ya que se esta generando requerimiento de compras, intenta de nuevo en 3 minutos.", 2, $ObjVista);
            return false;
        }
    }

    /**
     * Descripcion: Cambia la linea grupo y subgrupo cuando son editados en item y lista costos det
     * @param type $Item
     * @param type $IdLista
     */
    public static function ValidarLineaGrupoSubGrupo($Item = '', $IdLista = '') {
        if ($Item != '') {
            $DetItem = new ItemRecord();
            $DetItem = ItemRecord::finder()->FindByPk($Item);
            if ($DetItem->IdListaCostosDetItem > 0) {
                $LCdet = new ListaCostosProvDetRecord();
                $LCdet = ListaCostosProvDetRecord::finder()->FindByPk($DetItem->IdListaCostosDetItem);
                if ($LCdet->IdLineaLC != $DetItem->IdLinea) {
                    $LCdet->IdLineaLC = $DetItem->IdLinea;
                    $LCdet->save();
                }

                if ($LCdet->IdGrupoLC != $DetItem->IdGrupo) {
                    $LCdet->IdGrupoLC = $DetItem->IdGrupo;
                    $LCdet->save();
                }

                if ($LCdet->IdSubGrupoLC != $DetItem->IdSubgrupo) {
                    $LCdet->IdSubGrupoLC = $DetItem->IdSubgrupo;
                    $LCdet->save();
                }
            }
        }

        if ($IdLista != '') {
            $DetLista = new ListaCostosProvDetRecord();
            $DetLista = ListaCostosProvDetRecord::finder()->FindByPk($IdLista);
            if ($DetLista->Id_Item > 0) {
                $DetItem = new ItemRecord();
                $DetItem = ItemRecord::finder()->FindByPk($DetLista->Id_Item);
                if ($DetItem->IdLinea != $DetLista->IdLineaLC) {
                    $DetItem->IdLinea = $DetLista->IdLineaLC;
                    $DetItem->save();
                }

                if ($DetItem->IdGrupo != $DetLista->IdGrupoLC) {
                    $DetItem->IdGrupo = $DetLista->IdGrupoLC;
                    $DetItem->save();
                }

                if ($DetItem->IdSubgrupo != $DetLista->IdSubGrupoLC) {
                    $DetItem->IdSubgrupo = $DetLista->IdSubGrupoLC;
                    $DetItem->save();
                }
            }
        }
    }

    public static function GenerarAlertaSegundaAutorizacion($IdConcepto, $IdDocumento, $ObjVista, $Contrato = null) {
        $Comentario = '';
        $IdMovimiento = '';
        if ($IdDocumento != 2 && $IdDocumento != 44 && $IdDocumento != 59 && $IdDocumento != 82 && $IdDocumento != 90 && $IdDocumento != 91) {
            if ($ObjVista->TxtComentarioAut->Text == '' || $IdConcepto == '') {
                funciones::Mensaje("No se puede crear la solicitud por que debes escribir un comentario y seleccionar un concepto.", 2, $ObjVista);
                return false;
            }
            //Creamos esta validacion para los productos que se va cambiar la cantidad
            if (($IdDocumento == 3 || $IdDocumento == 11 || $IdDocumento == 12 || $IdDocumento == 1 || $IdDocumento == 87) && ($IdConcepto == 32 || $IdConcepto == 33 || $IdConcepto == 40 || $IdConcepto == 44 || $IdConcepto == 47 || $IdConcepto == 48)) {
                for ($i = 0; $i < $ObjVista->DGMovDet->ItemCount; $i++) {
                    if ($ObjVista->DGMovDet->Items[$i]->ClmSeleccionar->Check->Checked == true) {
                        $Comentario = $Comentario . "<br>[" . $ObjVista->DGMovDet->Items[$i]->ClmIdItem->Text . " =>" . $ObjVista->DGMovDet->Items[$i]->ClmIdMovDet->LnkIdMovDet->Text . "] (" . $ObjVista->DGMovDet->Items[$i]->ClmDescripcion->Text . ")<br> - ";
                    }
                }
                if ($Comentario == '') {
                    funciones::Mensaje("No puedes crear la solicitud sin seleccionar almenos 1 producto.", 2, $ObjVista);
                    return false;
                }
            }
            $Val = SegundasAutorizacionesDocumentosRecord::finder()->FindBy_IdDocumento_and_IdMovimiento_and_IdConceptoAut_and_Activo_and_Eliminado($IdDocumento, $ObjVista->LblIdMovimiento->Text, $IdConcepto, 1, 0);
            if (count($Val) <= 0) {
                $Mov = new MovimientosRecord();
                $Mov = MovimientosRecord::finder()->FindByPk($ObjVista->LblIdMovimiento->Text);
                $Val = true;
                //Validamos que si es una solicitud de devolucion valide si ya tiene un documento soporte. a peticion de Eliza.
                if ($IdConcepto == 16 && $Mov->TpDocumento == 16) {
                    $Val = DocDigitalizadosRecord::BuscarDoc($Mov->NroDocumento, $Mov->IdDocumento, false, $Mov->IdTercero, '', '');
                    if (count($Val) <= 0) {
                        $Val = false;
                    }
                }

                if ($Val == true) {
                    $Alerta = new SegundasAutorizacionesDocumentosRecord();
                    $Alerta->IdMovimiento = $ObjVista->LblIdMovimiento->Text;
                    $Alerta->IdDocumento = $IdDocumento;
                    $Alerta->FhCreacion = date("Y-m-d H:i:s");
                    $Alerta->IdConceptoAut = $IdConcepto;
                    if ($Comentario == '') {
                        $Alerta->Comentarios = $ObjVista->TxtComentarioAut->Text;
                    } else {
                        $Alerta->Comentarios = $ObjVista->TxtComentarioAut->Text . " " . $Comentario;
                    }
                    $Alerta->IdUsuario = $ObjVista->User->Name;
                    $Alerta->Activo = 1;
                    if ($Alerta->save()) {
                        funciones::Mensaje("Se ha creado correctamente la  solicitud.", 2, $ObjVista);
                        funciones::AlertaNotificacion(1, $Alerta->IdAut);
                    }
                } else {
                    funciones::Mensaje("La solicitud todavía no tiene un documento soporte para poder solicitar la autorización.", 2, $ObjVista);
                    return false;
                }
            } else {
                funciones::Mensaje("Ya existe una solicitud para el documento por el mismo concepto pendiente de autorización.", 2, $ObjVista);
            }
        } else if ($IdDocumento == 44 || $IdDocumento == 2 || $IdDocumento == 90 || $IdDocumento == 91) {
            $Doc = '';
            $ComentarioSol = "";
            if ($IdDocumento == 44) {
                $Doc = $ObjVista->LblIdRecibo->Text;
                $ComentarioSol = "El documento requiere de una segunda autorización por descuentos Adicionales, generado por el usuario (a) :" . $ObjVista->User->Name;
            }
            if ($IdDocumento == 2) {
                $Doc = $ObjVista->LblIdCotizacion->Text;
                $ComentarioSol = $ObjVista->TxtComentarioAut->Text;
            }
            if ($IdDocumento == 90) {
                $Doc = $ObjVista->Request["IdSol"];
                $ComentarioSol = $ObjVista->TxtComentarios->Text;
            }
            if ($IdDocumento == 91) {
                $Doc = $ObjVista->Request["IdActa"];
                $ComentarioSol = "Solicito aprobación del acta de apertura";
            }
            $Val = SegundasAutorizacionesDocumentosRecord::finder()->FindBy_IdDocumento_and_IdMovimiento_and_IdConceptoAut_and_Activo_and_Eliminado($IdDocumento, $Doc, $IdConcepto, 1, 0);
            if (count($Val) <= 0) {
                $Alerta = new SegundasAutorizacionesDocumentosRecord();
                $Alerta->IdMovimiento = $Doc;
                $Alerta->IdDocumento = $IdDocumento;
                $Alerta->FhCreacion = date("Y-m-d H:i:s");
                $Alerta->IdConceptoAut = $IdConcepto;
                $Alerta->Comentarios = $ComentarioSol;
                $Alerta->IdUsuario = $ObjVista->User->Name;
                $Alerta->Activo = 1;
                if ($Alerta->save()) {
                    funciones::Mensaje("Se ha creado correctamente la  solicitud.", 2, $ObjVista);
                    funciones::AlertaNotificacion(1, $Alerta->IdAut);
                    return true;
                }
            } else {
                funciones::Mensaje("Ya existe una solicitud para el documento por el mismo concepto pendiente de autorización.", 2, $ObjVista);
                return false;
            }
        } else if ($IdDocumento == 59) {
            $Doc = $Contrato;
            $Val = SegundasAutorizacionesDocumentosRecord::finder()->FindBy_IdDocumento_and_IdMovimiento_and_IdConceptoAut_and_Activo_and_Eliminado($IdDocumento, $Doc, $IdConcepto, 1, 0);
            if (count($Val) <= 0) {
                $Alerta = new SegundasAutorizacionesDocumentosRecord();
                $Alerta->IdMovimiento = $Doc;
                $Alerta->IdDocumento = $IdDocumento;
                $Alerta->FhCreacion = date("Y-m-d H:i:s");
                $Alerta->IdConceptoAut = $IdConcepto;
                $Alerta->Comentarios = 'Requiero permiso para generar el acta del contrato para el cliente.';
                $Alerta->IdUsuario = $ObjVista->User->Name;
                $Alerta->Activo = 1;
                if ($Alerta->save()) {
                    funciones::Mensaje("Se ha creado correctamente la  solicitud.", 2, $ObjVista);
                    funciones::AlertaNotificacion(1, $Alerta->IdAut);
                }
            } else {
                funciones::Mensaje("Ya existe una solicitud para el documento por el mismo concepto pendiente de autorización.", 2, $ObjVista);
            }
        } else if ($IdDocumento == 82) {
            $Val = SegundasAutorizacionesDocumentosRecord::finder()->FindBy_IdDocumento_and_IdMovimiento_and_IdConceptoAut_and_Activo_and_Eliminado($IdDocumento, $Contrato, $IdConcepto, 1, 0);
            if (count($Val) <= 0) {
                $Alerta = new SegundasAutorizacionesDocumentosRecord();
                $Alerta->IdMovimiento = $Contrato;
                $Alerta->IdDocumento = $IdDocumento;
                $Alerta->FhCreacion = date("Y-m-d H:i:s");
                $Alerta->IdConceptoAut = $IdConcepto;
                $Alerta->Comentarios = 'Aprobar la solicitud de provision.';
                $Alerta->IdUsuario = $ObjVista->User->Name;
                $Alerta->Activo = 1;
                if ($Alerta->save()) {
                    funciones::Mensaje("Se ha creado una solicitud para la  aprobacion d ela provision.", 2, $ObjVista);
                    funciones::AlertaNotificacion(1, $Alerta->IdAut);
                }
            }
        }
    }

    /**
     * @Des Se crea metodo para validar si un documento tiene una segunda autorizacion.
     * @param type $IdConcepto
     * @param type $IdMov
     * @param type $IsMov
     * @param type $Objvista
     */
    public static function ValidarSegundaAutorizacion($IdConcepto, $IdMov, $IsMov, $Objvista, $Eliminar = false) {
        if (is_numeric($IsMov) && $IsMov == 44) {//Validamos los recibos aqui podemos arreglar la variable ismovdet para pasarle valores para otros tipos de documentos.
            $Mov = new RecibosRecord();
            $Mov = RecibosRecord::finder()->findByPk($IdMov);
        } else if (is_numeric($IsMov) && $IsMov == 2) {//Valida las cotizaciones
            $Mov = new CotizacionesRecord();
            $Mov = CotizacionesRecord::finder()->findByPk($IdMov);
        } else if (is_numeric($IsMov) && $IsMov == 59) {
            $Mov = new ContratosRecord();
            $Mov = ContratosRecord::finder()->findByPk($IdMov);
        } else if (is_numeric($IsMov) && $IsMov == 90) {
            $Mov = new SolicitudesComodatosRecord();
            $Mov = SolicitudesComodatosRecord::finder()->findByPk($IdMov);
        } else if (is_numeric($IsMov) && $IsMov == 91) {
            $Mov = new ActasAperturaComodatosRecord();
            $Mov = ActasAperturaComodatosRecord::finder()->findByPk($IdMov);
        } else {// de lo contrario son movimientos.
            $Mov = new MovimientosRecord();
            $Mov = MovimientosRecord::finder()->FindByPk($IdMov);
        }
        $IdDoc = 0;
        if (!is_bool($IsMov) && $IsMov == 91) {
            $IdDoc = 91;
        } else {
            $IdDoc = $Mov->IdDocumento;
        }
        $Val = new SegundasAutorizacionesDocumentosRecord();
        $Sql = "select * from segundas_autorizaciones_documentos where IdMovimiento =$IdMov and IdDocumento =" . $IdDoc . "  and IdConceptoAut =$IdConcepto and Eliminado =0";
        $Val = SegundasAutorizacionesDocumentosRecord::finder()->FindBySql($Sql);
        if (count($Val) > 0 && $Val->Autorizado2 == 1) {
            if ($IdDoc == 59) {
                SegundasAutorizacionesDocumentosRecord::finder()->DeleteByPk($Val->IdAut);
            }
            if ($Eliminar == true) {
                $Val->Eliminado = 1;
                $Val->Activo = 0;
                $Val->save();
            }
            return true;
        } else {
            return false;
        }
    }

    public static function ValidarExisteSegundaAutorizacion($IdConcepto, $IdMov, $IsMov, $Objvista) {
        if (is_numeric($IsMov) && $IsMov == 44) {//Validamos los recibos aqui podemos arreglar la variable ismovdet para pasarle valores para otros tipos de documentos.
            $Mov = new RecibosRecord();
            $Mov = RecibosRecord::finder()->findByPk($IdMov);
        } else {// de lo contrario son movimientos.
            $Mov = new MovimientosRecord();
            $Mov = MovimientosRecord::finder()->FindByPk($IdMov);
        }
        $Val = new SegundasAutorizacionesDocumentosRecord();
        $Sql = "select * from segundas_autorizaciones_documentos where IdMovimiento =$IdMov and IdDocumento =$Mov->IdDocumento  and IdConceptoAut =$IdConcepto and Eliminado =0";
        $Val = SegundasAutorizacionesDocumentosRecord::finder()->FindBySql($Sql);
        if (count($Val) > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 
     * @param type $Item
     * @param type $IdMov
     * @param type $IdConcepto
     * @param type $ObVista
     * @return boolean
     */
    public function ValidarItemAutorizacionDoc($Item, $IdMov, $IdConcepto, $ObVista) {
        if (funciones::ValidarSegundaAutorizacion($IdConcepto, $IdMov, true, $ObVista) == true) {
            $Mov = new MovimientosRecord();
            $Mov = MovimientosRecord::finder()->FindByPk($IdMov);
            $Val = new SegundasAutorizacionesDocumentosRecord();
            $Sql = "select * from segundas_autorizaciones_documentos where IdMovimiento =" . $Mov->IdMovimiento . " and IdDocumento =$Mov->IdDocumento  and IdConceptoAut =" . $IdConcepto . " and Eliminado =0";
            $Val = SegundasAutorizacionesDocumentosRecord::finder()->FindBySql($Sql);
            if (strpos($Val->Comentarios, $Item) !== false) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @Desc Envia la notificacion sobre las autorizaciones al usuario y a los responsables.
     * @param type $Op
     * @param type $IdAut
     * 2017/12/05
     */
    public static function AlertaNotificacion($Op, $IdAut) {
        $Aut = new SegundasAutorizacionesDocumentosRecord();
        $Aut = SegundasAutorizacionesDocumentosRecord::finder()->FindByPk($IdAut);
        $IdMov = '';
        $Numero = '';
        if ($Aut->IdDocumento != 44 && $Aut->IdDocumento != 2 && $Aut->IdDocumento != 59 && $Aut->IdDocumento != 82 && $Aut->IdDocumento != 90 && $Aut->IdDocumento != 91) {
            $Mov = new MovimientosRecord();
            $Mov = MovimientosRecord::finder()->FindByPk($Aut->IdMovimiento);
            $IdMov = $Mov->IdMovimiento;
            $Numero = $Mov->NroDocumento;
        } else if ($Aut->IdDocumento == 44) {
            $Mov = new RecibosRecord();
            $Mov = RecibosRecord::finder()->FindByPk($Aut->IdMovimiento);
            $IdMov = $Mov->IdRecibo;
            $Numero = $Mov->NroRecibo;
        } else if ($Aut->IdDocumento == 2) {
            $Mov = new CotizacionesRecord();
            $Mov = CotizacionesRecord::finder()->FindByPk($Aut->IdMovimiento);
            $IdMov = $Mov->IdCotizacion;
            $Numero = $Mov->NroCotizacion;
        } else if ($Aut->IdDocumento == 59) {
            $Mov = new ContratosRecord();
            $Mov = ContratosRecord::finder()->FindByPk($Aut->IdMovimiento);
            $IdMov = $Mov->IdContrato;
            $Numero = $Mov->NroContrato;
        } else if ($Aut->IdDocumento == 82) {
            $Mov = new ProvisionesRecord();
            $Mov = ProvisionesRecord::finder()->FindByPk($Aut->IdMovimiento);
            $IdMov = $Mov->IdProvision;
            $Numero = $Mov->NmProvision;
        } else if ($Aut->IdDocumento == 90) {
            $Mov = new SolicitudesComodatosRecord();
            $Mov = SolicitudesComodatosRecord::finder()->FindByPk($Aut->IdMovimiento);
            $IdMov = $Mov->IdSolicitud;
            $Numero = $Mov->NroDocumento;
        } else if ($Aut->IdDocumento == 91) {
            $Mov = new ActasAperturaComodatosRecord();
            $Mov = ActasAperturaComodatosRecord::finder()->FindByPk($Aut->IdMovimiento);
            $IdMov = $Mov->IdActa;
            $Numero = $Mov->IdActa;
        }

        $Doc = new DocumentosRecord();
        $Doc = DocumentosRecord::finder()->FindByPk($Aut->IdDocumento);
        $Concepto = new ConceptosSegundaAutorizacionRecord();
        $Concepto = ConceptosSegundaAutorizacionRecord::finder()->FindByPk($Aut->IdConceptoAut);
        $UsrConceptos = PermisosConceptosAutorizacionesRecord::finder()->FindAllBy_IdConcepto_and_Activo($Aut->IdConceptoAut, 1);
        $Tercero = new TercerosRecord();
        if ($Aut->IdDocumento == 91) {
            $Tercero = TercerosRecord::finder()->FindByPk($Mov->IdTercero);
        } else if ($Mov->IdDocumento == 2) {
            $Tercero = TercerosRecord::finder()->FindByPk($Mov->IdTerceroCotizacion);
        } else if ($Mov->IdDocumento == 59) {
            $Tercero = TercerosRecord::finder()->FindByPk($Mov->IdContratista);
        } else if ($Mov->IdDocumento == 82) {
            $Tercero = TercerosRecord::finder()->FindByPk($Mov->IdTerceroProvision);
        } else {
            $Tercero = TercerosRecord::finder()->FindByPk($Mov->IdTercero);
        }
        $Acccion = '';
        if ($Aut->Autorizado2 == 1) {
            $Acccion = "Autorizado";
        } else if ($Aut->Eliminado == 1) {
            $Acccion = "Descartado";
        }
        $strEmail = '';
        if ($Op == 1) {
            $strAsunto = "Alerta Solicitud  Autorizacion ";
            $strMensaje = "Este es un nuevo mensaje enviado desde ABA Cientifica, el cual le informa que en la fecha y hora " . date("Y-m-d  H:i:s") . " 
                        el usuario " . strtoupper($Aut->IdUsuario) . " solicito segunda autorización para el documento " . strtoupper($Doc->Nombre) . " con Id :" . $IdMov . " y # " . $Numero . " por el concepto de  '" . $Concepto->NmConceptoAut . "' para el cliente :" . $Tercero->NombreCorto . ", 
                        si desea realizarle seguimiento a esta accion busque el documento y verifique en el sistema Kasten.
                        <br>
                        Muchas gracias por la atención,
                        <br> 
                        <br>
                        <br> 
                        Este correo es enviado automaticamente desde el sistema, por favor absténgase de responderlo, cualquier inquietud comuniquese con sistemas@aba.com.co - auxsistemas@aba.com.co";

            if (count($UsrConceptos) > 0) {
                foreach ($UsrConceptos as $Mail) {
                    $Correo = UsuarioRecord::finder()->FindByPk($Mail->IdUsuario);
                    if ($Correo->Email != '') {
                        $strEmail[] = $Correo->Email;
                    }
                }
            }
        } else if ($Op == 2) {
            $strAsunto = "Alerta Solicitud Segunda Autorizacion " . $Concepto->NmConceptoAut . " fue " . $Acccion;
            $strMensaje = "Este es un nuevo mensaje enviado desde ABA Cientifica, el cual le informa que en la fecha y hora " . date("Y-m-d  H:i:s") . " 
                        se ha " . $Acccion . " la solicitud  de autorizacion en el documento " . strtoupper($Doc->Nombre) . " con IdMovimiento " . $IdMov . " por el concepto de  '" . $Concepto->NmConceptoAut . "' para el cliente :" . $Tercero->NombreCorto . ", 
                        para mas información comuniquese con el usuario que realizo la acción '" . strtoupper($Aut->IdUsuarioAut) . "'.<br>
                        ";
            if ($Acccion == "Descartado") {
                $strMensaje .= "<strong>Comentarios :</strong> " . $Aut->Comentarios2;
            }
            $strMensaje .= "<br>
                        Muchas gracias por la atención,
                        <br> 
                        <br>
                        <br> 
                        Este correo es enviado automaticamente desde el sistema, por favor absténgase de responderlo, cualquier inquietud comuniquese con sistemas@aba.com.co - auxsistemas@aba.com.co";
            $Usuario = new UsuarioRecord();
            $Usuario = UsuarioRecord::finder()->findByPk($Aut->IdUsuario);
            $strEmail[] = $Usuario->Email;
        }
        if (!empty($strEmail)) {
            funciones::EnviarCorreo(true, "kasten@aba.com.co", "Kasten - ABA Cientifica", $strAsunto, $strMensaje, $strEmail);
        }
    }

    public static function NotificacionesAlertas($ObjVista) {
        try {
            $Permisos = PermisosConceptosAutorizacionesRecord::PermisosUsuariosConceptos($ObjVista->User->Name);
            $Datos = new SegundasAutorizacionesDocumentosRecord();
            $Sql = "select  segundas_autorizaciones_documentos.* from segundas_autorizaciones_documentos "
                    . "LEFT JOIN movimientos on movimientos.IdMovimiento = segundas_autorizaciones_documentos.IdMovimiento "
                    . "LEFT JOIN documentos on documentos.IdDocumento = segundas_autorizaciones_documentos.IdDocumento "
                    . "LEFT JOIN terceros on terceros.IdTercero = movimientos.IdTercero "
                    . "LEFT JOIN conceptos_segunda_autorizacion on conceptos_segunda_autorizacion.IdConceptoAut = segundas_autorizaciones_documentos.IdConceptoAut"
                    . " WHERE segundas_autorizaciones_documentos.Activo = 1 and segundas_autorizaciones_documentos.Autorizado2=0 ";
            if ($Permisos != '') {
                for ($i = 0; $i < count($Permisos); $i++) {
                    if ($i == 0) {
                        $Sql = $Sql . " and ( conceptos_segunda_autorizacion.IdConceptoAut = " . $Permisos[$i]->IdConcepto;
                    } else {
                        $Sql = $Sql . " OR conceptos_segunda_autorizacion.IdConceptoAut=" . $Permisos[$i]->IdConcepto;
                    }
                }
                $Sql = $Sql . ")";
            }

            if ($Permisos == '' && funciones::IsAdmin($ObjVista->User->Name) == false) {
                $Sql = $Sql . " and segundas_autorizaciones_documentos.IdUsuario='" . $ObjVista->User->Name . "'";
            }
            $Datos = SegundasAutorizacionesDocumentosRecord::finder()->FindAllBySql($Sql);
            if (count($Datos) > 0) {
                $a = count($Datos);
                $ObjVista->TxtAlertas->Text = count($Datos);
                $ObjVista->TxtAlertas->BackColor = 'rgb(222, 112, 112)';
            }
            if (funciones::PermisosConsultas($ObjVista->User->Name, 74) && $ObjVista->User->Name == 'ruth') {
                $DatosPedidos = new MovimientosRecord();
                $SqlPed = "select movimientos.*,NombreCorto from  movimientos
                            LEFT JOIN terceros on terceros.IdTercero = movimientos.IdTercero
                            LEFT JOIN movimientos_det on movimientos_det.IdMovimiento = movimientos.IdMovimiento
                            where movimientos.IdDocumento = 8 and (movimientos.Estado ='AUTORIZADA')
                            AND terceros.BloqueadoCartera = 1 and movimientos_det.Estado='AUTORIZADO' AND movimientos_det.Confirmado=1 GROUP BY movimientos.IdMovimiento";
                $DatosPedidos = MovimientosRecord::finder('MovimientosExtRecord')->FindAllBySql($SqlPed);
                $ObjVista->TxtAlertas->Text = $ObjVista->TxtAlertas->Text . " - " . count($DatosPedidos);
                $ObjVista->TxtAlertas->Width = "50px";
                $ObjVista->TxtAlertas->BackColor = 'rgb(222, 112, 112)';
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Des  Genera las ventaas de los items por proveedor.
     * FhActualizacion 03/01/2017
     */
    public static function GenerarVentasProductos($Op = '0') {
        $FechaUl = new TempItemVentasProveedorRecord();
        $SqlFh = "select * from temp_item_ventas_proveedores ORDER BY  FhUltimaGen DESC limit 1";
        $FechaUl = TempItemVentasProveedorRecord::finder()->FindBySql($SqlFh);
        if (count($FechaUl) > 0) {
            $sql = "delete  from temp_item_ventas_proveedores where Anio >=DATE_FORMAT(CURDATE(),'%Y') and Mes = DATE_FORMAT(CURDATE(),'%m')";
            $command = TempItemVentasProveedorRecord::finder()->getDbConnection()->createCommand($sql);
            $command->execute();
        }
        if ($Op == 1) {// cuando se va regenerar 6 meses de ventas
            $sql = "delete  from temp_item_ventas_proveedores where Anio >=DATE_FORMAT(CURDATE(),'%Y') and Mes >= 4";
            $command = TempItemVentasProveedorRecord::finder()->getDbConnection()->createCommand($sql);
            $command->execute();
        }

        $FechaUl = new TempItemVentasProveedorRecord();
        $SqlFh = "select * from temp_item_ventas_proveedores ORDER BY  FhUltimaGen DESC limit 1";
        $FechaUl = TempItemVentasProveedorRecord::finder()->FindBySql($SqlFh);
        $FhDesde = '2018-04-01 06:00:00';
        if (count($FechaUl) > 0) {
//            $sql = "delete from temp_item_ventas_proveedores where (Anio >= (SELECT DATE_FORMAT(DATE_SUB('".$FechaUl->FhUltimaGen."',INTERVAL 1 MONTH),'%Y') )     and Mes >= (SELECT DATE_FORMAT(DATE_SUB('".$FechaUl->FhUltimaGen."',INTERVAL 3 MONTH),'%m')))";
//            $command = TempItemVentasProveedorRecord::finder()->getDbConnection()->createCommand($sql);
//            $command->execute();
            if ($FechaUl->FhUltimaGen >= '2018-01-01 06:00:00') {
                $FhDesde = $FechaUl->FhUltimaGen;
            } else {
                $FhDesde = '2018-04-01 06:00:00';
            }
//            $fecha = $FhDesde;
//            $nuevafecha = strtotime ( '-3 month' , strtotime ( $fecha ) ) ;
//            $nuevafecha = date ( 'Y-m-d H:i:s' , $nuevafecha );
//            $FhDesde = $nuevafecha;
        } else {
            $sql = "delete  from temp_item_ventas_proveedores where Anio >= 2018";
            $command = TempItemVentasProveedorRecord::finder()->getDbConnection()->createCommand($sql);
            $command->execute();
        }

        $Sql = "select item.Id_Item AS Id_Item,date_format(movimientos_det.FechaDet,'%Y') AS Anio,date_format(movimientos_det.FechaDet,'%m' )AS Mes,
                sum(movimientos_det.CantOperada*-1) AS CantOperada,movimientos_det.IdTercero AS IdTercero,terceros.NombreCorto AS NombreCorto,SUM(movimientos_det.SubTotal) as 
                SubTotal 
                from item left join movimientos_det on movimientos_det.Id_Item = item.Id_Item left join movimientos_det movimientos_det_enlace on movimientos_det.Enlace = movimientos_det_enlace.IdMovimientoDet 
                left join movimientos on movimientos.IdMovimiento = movimientos_det.IdMovimiento 
                left join terceros on terceros.IdTercero = movimientos.IdTercero 
                LEFT JOIN documentos on documentos.IdDocumento = movimientos_det.IdDocumento
                where (movimientos_det.TpDocumento = 5 or (movimientos_det.TpDocumento = 7  and  movimientos_det.Cantidad-movimientos_det.CantAfectada !=0))
                and (movimientos_det.Estado <> 'DIGITADO' and movimientos_det.Estado <> 'ANULADO')";
        if ($Op == 1) {
            $Sql .= " and movimientos.FhAutoriza >='" . $FhDesde . "'";
        } else {
            $Sql .= " and movimientos.FhAutoriza >= CONCAT(DATE_FORMAT(CURDATE(),'%Y'),'-',DATE_FORMAT(CURDATE(),'%m'),'-','01')";
        }
        $Sql .= " group by item.Id_Item,date_format(movimientos_det.FechaDet,'%Y'),date_format(movimientos_det.FechaDet,'%m'),IdTercero ORDER BY Id_Item";
        $Datos = TempItemVentasProveedorRecord::finder('TempItemVentasProveedorExtRecord')->FindAllBySql($Sql);
        $TReg = count($Datos);
        $Cont = 0;
        if (count($Datos) > 0) {
            foreach ($Datos as $Datos) {
                $a = $Datos->Mes;
                $b = $Datos->Anio;
                $c = $Datos->CantOperada;
                $Cont = $Cont + 1;
                $DatosAdd = new TempItemVentasProveedorRecord();
                $Sql = "select * from temp_item_ventas_proveedores where Id_Item=" . $Datos->Id_Item . " and Anio =" . $Datos->Anio . " and Mes =" . $Datos->Mes . " and IdTercero =" . $Datos->IdTercero;
                $DatosAdd = TempItemVentasProveedorRecord::finder()->FindBySql($Sql);
                if (count($DatosAdd) > 0) {
                    if ($Datos->Mes == 1) {
                        $DatosAdd->Enero = $DatosAdd->Enero + $Datos->CantOperada;
                        $DatosAdd->TEnero = $DatosAdd->TEnero + $Datos->SubTotal;
                    }

                    if ($Datos->Mes == 2) {
                        $DatosAdd->Febrero = $DatosAdd->Febrero + $Datos->CantOperada;
                        $DatosAdd->TFebrero = $DatosAdd->TFebrero + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 3) {
                        $DatosAdd->Marzo = $DatosAdd->Marzo + $Datos->CantOperada;
                        $DatosAdd->TMarzo = $DatosAdd->TMarzo + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 4) {
                        $DatosAdd->Abril = $DatosAdd->Abril + $Datos->CantOperada;
                        $DatosAdd->TAbril = $DatosAdd->TAbril + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 5) {
                        $DatosAdd->Mayo = $DatosAdd->Mayo + $Datos->CantOperada;
                        $DatosAdd->TMayo = $DatosAdd->TMayo + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 6) {
                        $DatosAdd->Junio = $DatosAdd->Junio + $Datos->CantOperada;
                        $DatosAdd->TJunio = $DatosAdd->TJunio + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 7) {
                        $DatosAdd->Julio = $DatosAdd->Julio + $Datos->CantOperada;
                        $DatosAdd->TJulio = $DatosAdd->TJulio + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 8) {
                        $DatosAdd->Agosto = $DatosAdd->Agosto + $Datos->CantOperada;
                        $DatosAdd->TAgosto = $DatosAdd->TAgosto + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 9) {
                        $DatosAdd->Septiembre = $DatosAdd->Septiembre + $Datos->CantOperada;
                        $DatosAdd->TSeptiembre = $DatosAdd->TSeptiembre + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 10) {
                        $DatosAdd->Octubre = $DatosAdd->Octubre + $Datos->CantOperada;
                        $DatosAdd->TOctubre = $DatosAdd->TOctubre + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 11) {
                        $DatosAdd->Noviembre = $DatosAdd->Noviembre + $Datos->CantOperada;
                        $DatosAdd->TNoviembre = $DatosAdd->TNoviembre + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 12) {
                        $DatosAdd->Diciembre = $DatosAdd->Diciembre + $Datos->CantOperada;
                        $DatosAdd->TDiciembre = $DatosAdd->TDiciembre + $Datos->SubTotal;
                    }
                    $DatosAdd->Save();
                } else {
                    $DatosAdd = new TempItemVentasProveedorRecord();
                    $DatosAdd->Id_Item = $Datos->Id_Item;
                    $DatosAdd->IdTercero = $Datos->IdTercero;
                    $DatosAdd->Anio = $Datos->Anio;
                    $DatosAdd->Mes = $Datos->Mes;
                    if ($Datos->Mes == 1) {
                        $DatosAdd->Enero = $DatosAdd->Enero + $Datos->CantOperada;
                        $DatosAdd->TEnero = $DatosAdd->TEnero + $Datos->SubTotal;
                    }

                    if ($Datos->Mes == 2) {
                        $DatosAdd->Febrero = $DatosAdd->Febrero + $Datos->CantOperada;
                        $DatosAdd->TFebrero = $DatosAdd->TFebrero + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 3) {
                        $DatosAdd->Marzo = $DatosAdd->Marzo + $Datos->CantOperada;
                        $DatosAdd->TMarzo = $DatosAdd->TMarzo + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 4) {
                        $DatosAdd->Abril = $DatosAdd->Abril + $Datos->CantOperada;
                        $DatosAdd->TAbril = $DatosAdd->TAbril + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 5) {
                        $DatosAdd->Mayo = $DatosAdd->Mayo + $Datos->CantOperada;
                        $DatosAdd->TMayo = $DatosAdd->TMayo + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 6) {
                        $DatosAdd->Junio = $DatosAdd->Junio + $Datos->CantOperada;
                        $DatosAdd->TJunio = $DatosAdd->TJunio + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 7) {
                        $DatosAdd->Julio = $DatosAdd->Julio + $Datos->CantOperada;
                        $DatosAdd->TJulio = $DatosAdd->TJulio + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 8) {
                        $DatosAdd->Agosto = $DatosAdd->Agosto + $Datos->CantOperada;
                        $DatosAdd->TAgosto = $DatosAdd->TAgosto + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 9) {
                        $DatosAdd->Septiembre = $DatosAdd->Septiembre + $Datos->CantOperada;
                        $DatosAdd->TSeptiembre = $DatosAdd->TSeptiembre + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 10) {
                        $DatosAdd->Octubre = $DatosAdd->Octubre + $Datos->CantOperada;
                        $DatosAdd->TOctubre = $DatosAdd->TOctubre + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 11) {
                        $DatosAdd->Noviembre = $DatosAdd->Noviembre + $Datos->CantOperada;
                        $DatosAdd->TNoviembre = $DatosAdd->TNoviembre + $Datos->SubTotal;
                    }
                    if ($Datos->Mes == 12) {
                        $DatosAdd->Diciembre = $DatosAdd->Diciembre + $Datos->CantOperada;
                        $DatosAdd->TDiciembre = $DatosAdd->TDiciembre + $Datos->SubTotal;
                    }
                    $DatosAdd->Save();
                }
                if ($Cont == $TReg) {
                    $DatosAdd->FhUltimaGen = date("Y-m-d H:i:s");
                    $DatosAdd->save();
                }
            }
            RegistroEventosRecord::RegistrarEvento("Proceso de actualizar ventas item por proveedor se actualizo correctamente.");
        }
    }

    /**
     * 
     * @param type $IdMovimiento
     * @param boolean $IsMov
     * @param type $IdDoc
     * @return string
     * Desc: Valida si un movimiento o recibo tien solicitud.
     */
    public static function ValidarExisteSolicitudesActivas($IdMovimiento, $IsMov, $IdDoc = '') {
        if ($IsMov == false) {
            $Mov = new RecibosRecord();
            $Mov = RecibosRecord::finder()->findByPk($IdMovimiento);
        } else {// de lo contrario son movimientos.
            $Mov = new MovimientosRecord();
            $Mov = MovimientosRecord::finder()->FindByPk($IdMovimiento);
        }
        $Val = new SegundasAutorizacionesDocumentosRecord();
        $Sql = "select * from segundas_autorizaciones_documentos where IdMovimiento =$IdMovimiento and IdDocumento =$Mov->IdDocumento  and Eliminado =0 and Activo =1 and Autorizado2 =0";
        $Val = SegundasAutorizacionesDocumentosRecord::finder()->FindBySql($Sql);
        if (count($Val) > 0) {
            return $Val;
        } else {
            return '';
        }
    }

    /**
     * Desc : Metodo para validar la cantidad afectada de los movimientos enlace.
     * @param type $IdDoc
     * 15/02/2018
     */
    public static function ValidarAfectadasMov($IdMov) {
        $MovDet = new MovimientosDetRecord();
        $MovDet = MovimientosDetRecord::finder()->FindAllBy_IdMovimiento($IdMov);
        if (count($MovDet) > 0) {
            foreach ($MovDet as $Row) {
                if ($Row->Enlace > 0) {
                    $MovDetEnl = MovimientosDetRecord::finder()->FindByPk($Row->Enlace);
                    if (count($MovDetEnl) > 0) {
                        $MovDetsEnl = new MovimientosDetRecord();
                        $Sql = "select sum(Cantidad - CantAfectada) as Cantidad from movimientos_det LEFT JOIN documentos on documentos.IdDocumento = movimientos_det.IdDocumento where Enlace = " . $Row->Enlace . " and  documentos.AfectaCantRef=1  and (movimientos_det.Estado='AUTORIZADO' or movimientos_det.Estado='CERRADO')";
                        $MovDetsEnl = MovimientosDetRecord::finder()->FindBySql($Sql);
                        if ($MovDetEnl->CantAfectada != $MovDetsEnl->Cantidad || $MovDetsEnl->Cantidad > $MovDetEnl->CantAfectada) {
                            if ($MovDetsEnl->Cantidad == $MovDetEnl->Cantidad || $MovDetsEnl->Cantidad > $MovDetEnl->CantAfectada) {
                                $MovDetEnl->CantAfectada = $MovDetsEnl->Cantidad;
                                $MovDetEnl->save();
                                if ($MovDetsEnl->Cantidad > $MovDetEnl->Cantidad) {
                                    $EmailUsrMov;
                                    $Mov = MovimientosRecord::finder()->FindByPk($MovDetEnl->IdMovimiento);
                                    if (count($Mov) > 0 && $Mov->IdUsuario != '') {
                                        $usr = UsuarioRecord::finder()->FindByPk($Mov->IdUsuario);
                                        if ($usr->Email != '') {
                                            $EmailUsrMov = $usr->Email;
                                        }
                                    }
                                    $documento = DocumentosRecord::finder()->FindByPK($Mov->IdDocumento);
                                    $tercero = TercerosRecord::finder()->FindByPk($MovDetEnl->IdTercero);
                                    $strAsunto = "Alerta Cantidades Afectadas Mayores al Documento Inicial";
                                    $strMensaje = " Este es un nuevo mensaje enviado desde ABA Cientifica, el cual le informa que del item  " . $MovDetEnl->Id_Item . " y " . $documento->Nombre . " # " . $MovDetEnl->NroDocumento . " para el cliente " . $tercero->NombreCorto . " la cantidad inicial era de " . $MovDetEnl->Cantidad . " y se han afectado " . $MovDetsEnl->Cantidad . ", por favor comunicarse de inmediato con el área de sistemas para corregir el problema,
                                        si ya el problema fue resuelto ignore este correo.<br><br>
                                        Quedamos al Pendiente,<br><br>
                                        Área de Sistemas ABA Cientifica";
                                    $strDirecciones[] = 'auxsistemas@aba.com.co';
                                    $strDirecciones[] = 'auxsoporte@aba.com.co';
                                    $strDirecciones[] = 'sistemas@aba.com.co';
                                    if (!empty($EmailUsrMov)) {
                                        $strDirecciones[] = $EmailUsrMov;
                                    }
                                    funciones::EnviarCorreo(true, "kasten@aba.com.co", "Kasten - ABA Cientifica", $strAsunto, $strMensaje, $strDirecciones);
                                }
                                MovimientosRecord::CerrarAutoMov($MovDetEnl->IdMovimiento);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Se crea metodo para generar una gestion desde cualquier documento del sistema.
     * @param type $ObjVista
     * @param type $IdDoc
     * @param type $Mov
     */
    public static function NuevaGestionDoc($ObjVista, $IdDoc = '', $Mov) {
        $IdTercero = "";
        $AsesorCot = "";
        if ($IdDoc == 59) {
            $Movimiento = new ContratosRecord();
            $Movimiento = ContratosRecord::finder()->FindByPk($Mov);
            $IdTercero = $Movimiento->IdContratante;
        } else if ($IdDoc == 44) {
            $Movimiento = new RecibosRecord();
            $Movimiento = RecibosRecord::finder()->FindByPk($Mov);
            $IdTercero = $Movimiento->IdTercero;
        } else if ($IdDoc == 2) {
            $Movimiento = new CotizacionesRecord();
            $Movimiento = CotizacionesRecord::finder()->FindByPk($Mov);
            $IdTercero = $Movimiento->IdTerceroCotizacion;
            $AsesorCot = $Movimiento->AsesorCotizacion;
        } else if ($IdDoc != '') {
            $Movimiento = new MovimientosRecord();
            $Movimiento = MovimientosRecord::finder()->findByPk($Mov);
            $IdTercero = $Movimiento->IdTercero;
        } else if ($IdDoc == '' && $Mov != '') {
            $IdTercero = $Mov;
        }
        $Tercero = new TercerosRecord();
        $Tercero = TercerosRecord::finder()->FindByPK($IdTercero);
        $Asesor = new AsesoresRecord();
        if ($AsesorCot != '') {
            $Asesor = AsesoresRecord::finder()->FindByPk($AsesorCot);
        } else {
            $Asesor = AsesoresRecord::finder()->FindByPk($Tercero->IdAsesor);
        }
        $Usr = new UsuarioRecord();
        $Usr = UsuarioRecord::finder()->FindByPk($Asesor->UsuarioAsesor);
        $arGestionCartera = GestionesRecord::finder()->FindAllBy_IdDocumento_and_IdMovimiento($IdDoc, $Mov);
        if (count($arGestionCartera) <= 0 || count($arGestionCartera) > 0 && ($arGestionCartera[0]->Estado != "DIGITADA" && $arGestionCartera[0]->Estado != "AUTORIZADA")) {
            $arGestionCartera = new GestionesRecord();
            $arGestionCartera->Estado = "AUTORIZADA";

            //Poblamos los campos del objeto con los datos de los campos. 
            $arGestionCartera->Tipo = 'EXTERNA';
            $arGestionCartera->Medio = 'ELECTRONICA';
            $arGestionCartera->Fecha = date("Y-m-d");
            $arGestionCartera->Asunto = "SEGUMIENTO A LA COTIZACIÓN RESPUESTA DEL CLIENTE.";
            $arGestionCartera->Responsable = $Asesor->UsuarioAsesor;
            $arGestionCartera->Usuario = $ObjVista->User->Name;
            $arGestionCartera->Respuesta = '';
            $arGestionCartera->IdTercero = $IdTercero;
            $arGestionCartera->Contacto = '';
            $arGestionCartera->Cargo = '';
            $arGestionCartera->IdDocumento = $IdDoc;
            $arGestionCartera->IdMovimiento = $Mov;
            $arGestionCartera->IdClasificacion = 1;
            $arGestionCartera->save();
        } else {
            $arGestionCartera = GestionesRecord::finder()->FindByPk($arGestionCartera[0]->IdGestion);
        }

        //Almacenamos en base de datos.
        if ($arGestionCartera->IdGestion != '') {
            $arCompromiso = new ActividadesGestionesRecord();
            $arCompromiso->Descripcion = 'Confirmar con el cliente la aceptación de la propuesta y registrar la respuesta.';
            $arCompromiso->Fecha = $arGestionCartera->Fecha;
            $arCompromiso->Fecha2 = funciones::OperacionesFechas($arGestionCartera->Fecha, '+', 5, 'day');
            $arCompromiso->Responsable = $Asesor->UsuarioAsesor;
            $arCompromiso->Cumplido = 0;
            $arCompromiso->IdPadre = $arGestionCartera->IdGestion;
            $arCompromiso->Tipo = 'GESTION';
            $arCompromiso->Categoria = 'Email';
            $arCompromiso->save();
            AgregarCompromiso::EnviarCompromisoAsignado($arCompromiso->IdCompromiso);
        }
    }

    /**
     * Des: Realiza operaciones con fechas sea sumando dias,meses,años o al contrario restando el resultado es la fecha final.
     * @param type $FechaIni = a la fecha a la que desea realizarle la operacion.
     * @param type $Op = si es + o - de fecha
     * @param type $DatoOp = a la cantidad que desea opera ej: 10 dias 0 10 meses pero solo int
     * @param type $TipoFh = el tipo de fecha que desea operar ej:days,month,year
     * Resultado Ej = '+2 Mont' || +6year o || -1 year
     * @return  nueva fecha
     * FhActualozacion 05/04/2018
     */
    public static function OperacionesFechas($FechaIni, $Op = '+', $DatoOp, $TipoFh) {
        $fecha = $FechaIni;
        $nuevafecha = strtotime($Op . $DatoOp . " " . $TipoFh, strtotime($fecha));
        $nuevafecha = date('Y-m-d H:i:s', $nuevafecha);

        return $nuevafecha;
    }

    public static function ObtenerConceptoAut($IdConcepto) {
        $Concepto = new ConceptosSegundaAutorizacionRecord();
        $Concepto = ConceptosSegundaAutorizacionRecord::finder()->FindByPk($IdConcepto);
        if (count($Concepto) > 0) {
            return "CONCEPTO SEG. AUT : (" . $Concepto->NmConceptoAut . ")";
        } else {
            return "";
        }
    }

    public static function ObtenerIDAsesor($Usuario, $Op) {
        $Asesor = new AsesoresRecord();
        $Asesor = AsesoresRecord::finder()->FindBy_UsuarioAsesor($Usuario);
        if (count($Asesor) > 0) {
            if ($Op == 1) {//ID
                return $Asesor->IdAsesor;
            } else if ($Op == 2) {//Nombre
                return $Asesor->Nombre;
            } else if ($Op == 3) {//CC o nit
                return $Asesor->Identificacion;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    public static function DiasFestivos($Dia, $Mes) {
        $ArrayFestivos = array(
            '00-00');
        if (in_array($Mes . "-" . $Dia, $ArrayFestivos) == true) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * des: Se crea metodo para guardar el invima actual del producto.
     * @param IdMovimiento
     * FhActualizacion: 21/06/18
     */
    public static function ValidarDatosInvimaProducto($Movimiento) {
        $Mov = new MovimientosRecord();
        $Mov = MovimientosRecord::finder()->FindByPk($Movimiento);
        if (count($Mov) > 0 && $Mov->Estado != 'AUTORIZADA') {
            $RegMovimientoDet = new MovimientosDetRecord();
            $RegMovimientoDet = MovimientosDetRecord::finder()->FindAllBy_and_IdMovimiento($Movimiento);
            foreach ($RegMovimientoDet as $RegMovimientoDet) {
                $Item = new ItemRecord();
                $Item = ItemRecord::finder()->FindByPk($RegMovimientoDet->Id_Item);
                $ListaDet = new ListaCostosProvDetRecord();
                $ListaDet = ListaCostosProvDetRecord::finder()->FindByPk($Item->IdListaCostosDetItem);

                if (count($ListaDet) > 0) {
                    if (!empty($ListaDet->RegInvima) && $ListaDet->FhVenceInvima >= date("Y-m-d")) {
                        $RegMovimientoDet->RegistroInvima = $ListaDet->RegInvima;
                        $RegMovimientoDet->FhVenceReg = $ListaDet->FhVenceInvima;
                    } else if (!empty($ListaDet->RegInvimaCompuesto) && $ListaDet->FhVenceInvima >= date("Y-m-d")) {
                        $RegMovimientoDet->RegistroInvima = "RC- " . $ListaDet->RegInvimaCompuesto;
                        $RegMovimientoDet->FhVenceReg = $ListaDet->FhVenceInvima;
                    } else if (!empty($ListaDet->NroRadicadoInvima)) {
                        $RegMovimientoDet->CertificadoInvima = "REN -" . $ListaDet->NroRadicadoInvima;
                    } else if (!empty($ListaDet->CertAgotamientoEtiquetas)) {
                        $RegMovimientoDet->CertificadoInvima = "AGO -" . $ListaDet->CertAgotamientoEtiquetas;
                    }
                    $RegMovimientoDet->save();
                }
            }
        }
    }

    /**
     * Desc: El metodo valida que las cantidades que estan en el documento no sean mayores a la cantidad inicial o doc  enlace.
     * @param type $IdMov
     * @param type $ObjVista
     * @return boolean
     * Fh : 12/07/2018
     */
    public static function ValidarCantidadesEnlace($IdMov, $ObjVista) {
        $MovDet = new MovimientosDetRecord();
        $MovDet = MovimientosDetRecord::finder()->FindAllBy_IdMovimiento($IdMov);
        $Mov = new MovimientosRecord();
        $Mov = MovimientosRecord::finder()->with_Concepto()->FindByPk($IdMov);
        $StrItems = "";
        $CantIni = 0;
        $CantFin = 0;
        $Cantidad = 0;
        if (count($MovDet) > 0) {
            foreach ($MovDet as $Row) {
                if ($Row->Enlace > 0) {
                    $MovDetEnl = MovimientosDetRecord::finder()->FindByPk($Row->Enlace);
                    if (count($MovDetEnl) > 0) {
                        $Cantidad = $MovDetEnl->Cantidad;
                        $MovDetsEnl = new MovimientosDetRecord();
                        $Sql = "select CASE WHEN (movimientos_det.IdDocumento = 34 OR movimientos_det.IdDocumento = 72)
                                THEN sum(if(movimientos_det.Operacion = 0,Cantidad - CantAfectada,0))
                                ELSE SUM(Cantidad - CantAfectada) END as Cantidad  from movimientos_det LEFT JOIN documentos on documentos.IdDocumento = movimientos_det.IdDocumento where Enlace = " . $Row->Enlace . " and  documentos.AfectaCantRef=1  and (movimientos_det.Estado='AUTORIZADO' or movimientos_det.Estado='CERRADO' or movimientos_det.Estado='DIGITADO')";
                        $MovDetsEnl = MovimientosDetRecord::finder()->FindBySql($Sql);
                        $CantAfectar = 0;
                        if (count($MovDetsEnl) > 0 && $MovDetsEnl->Cantidad > 0) {
                            $CantAfectar = $MovDetsEnl->Cantidad;
                            if ($Mov->IdConcepto > 0 && $Mov->Concepto->Opcion == 1) {//Si el documento la opcion es averia o pruebas no consumidas hace la conversion de la cantidad fact vs la cantidad que se va a devolver.
                                $ItemR = new ItemRecord();
                                $ItemR = ItemRecord::finder()->FindByPK($MovDetEnl->Id_Item);
                                $ListaCDet = new ListaCostosProvDetRecord();
                                $ListaCDet = ListaCostosProvDetRecord::finder()->FindByPk($ItemR->IdListaCostosDetItem);
                                $Cantidad = ($MovDetEnl->Cantidad * $ListaCDet->CantContenido) == 0 ? 1 : ($MovDetEnl->Cantidad * $ListaCDet->CantContenido);
                            }
                        }
                        if (count($MovDetsEnl) > 0) {
                            if ($Cantidad < $CantAfectar) {
                                if (strpos($StrItems, $MovDetEnl->Id_Item) == false) {//Valida que el item no este en la cadena.
                                    $CantIni = $MovDetEnl->Cantidad;
                                    $CantFin = $MovDetsEnl->Cantidad;
                                    $StrItems = $StrItems . " -" . $MovDetEnl->Id_Item . " Cant. Ini:" . $CantIni . " Cant. Fin :" . $CantFin;
                                }
                            }
                        }
                    }
                }
            }
            if ($StrItems != '' && $Mov->IdConcepto != 179) {
                funciones::Mensaje("Los siguientes items : (" . $StrItems . ") tienen mayor cantidad a la del documento inicial/enlace, verifique  y corríjalo.", 2, $ObjVista);
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * Desc el metodo retorna el IdTercero proveedor del producto.
     * @param type $IdItem
     * @return int
     * Fh 19/07/2018
     */
    public static function DevProveedorItem($IdItem) {
        $Item = new ItemRecord();
        $Item = ItemRecord::finder()->FindByPk($IdItem);
        if (count($Item) > 0 && $Item->IdListaCostosDetItem > 0) {
            $ListaDet = new ListaCostosProvDetRecord();
            $ListaDet = ListaCostosProvDetRecord::finder()->With_ListaCostos()->FindByPk($Item->IdListaCostosDetItem);
            if (count($ListaDet) > 0) {
                return $ListaDet->ListaCostos->IdTercero;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    public static function ValidarGeneracionRequerimiento($IdMov, $IsMovDet) {
        $ValReq = new ConfiguracionesRecord();
        $ValReq = ConfiguracionesRecord::finder()->FindByPk(0);
        $BoolVal = false;
        $AfectaInv;
        if ($IsMovDet == true) {
            $Mov = new MovimientosDetRecord();
            $Mov = MovimientosDetRecord::finder()->With_Documento()->FindByPk($IdMov);
            $AfectaInv = $Mov->Documento->AfectaInventarios;
        } else {
            $Mov = new MovimientosRecord();
            $Mov = MovimientosRecord::finder()->With_DocumentoMov()->FindByPk($IdMov);
            $AfectaInv = $Mov->DocumentoMov->AfectaInventarios;
        }
        if ($ValReq->GenerandoRequerimiento == 1) {
            $BoolVal = true;
        } else {
            $BoolVal = false;
        }

        if ($BoolVal == true && $AfectaInv == 0) {
            $BoolVal = false;
        }
        return $BoolVal;
    }

    public static function ValidarPreciosMovimiento($IdMovimiento) {
        $Mov = new MovimientosRecord();
        $Mov = MovimientosRecord::finder()->FindByPk($IdMovimiento);
        $IdListaPrecios = '';
        if ($Mov->IdTercero > 0 && $Mov->IdDireccion > 0) {
            $IdListaPrecios = ListaPreciosRecord::DevListaPreciosTerceroDireccion($Mov->IdTercero, $Mov->IdDireccion);
        } else if ($Mov->IdTercero > 0) {
            $IdListaPrecios = ListaPreciosRecord::DevListaPreciosXTerceros($intIdTercero);
            $IdListaPrecios->IdListaPrecios;
        }
        if (count($IdListaPrecios) > 0) {
            $sql = "select lista_precios_det.Precio,movimientos_det.Precio as Id_Item from  lista_precios_det 
                    LEFT JOIN movimientos_det on movimientos_det.Id_Item = lista_precios_det.Id_Item
                    where IdListaPrecios = " . $IdListaPrecios . " and movimientos_det.IdMovimiento =" . $Mov->IdMovimiento . " and movimientos_det.Precio <> lista_precios_det.Precio";

            $ListaPrecios = ListaPreciosDetRecord::finder()->FindAllBySql($sql);
            if (count($ListaPrecios) > 0) {
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    public static function ValidarCantidadesMinimas($IdMovimiento, $ObjVista) {
        $MovDet = new MovimientosDetRecord();
        $MovDet = MovimientosDetRecord::finder()->FindAllBy_IdMovimiento($IdMovimiento);
        $Val = true;
        $Val2 = true;
        $ItemVal = '';
        if (count($MovDet) > 0) {
            foreach ($MovDet as $MovDet) {
                $Item = new ItemRecord();
                $Item = ItemRecord::finder()->FindByPk($MovDet->Id_Item);
                $ListaCostoDet = new ListaCostosProvDetRecord();
                $ListaCostoDet = ListaCostosProvDetRecord::finder('ListaCostosProvDetExtRecord')->findByPk($Item->IdListaCostosDetItem);

                if ($MovDet->Cantidad < $ListaCostoDet->CantMinimaCompra) {
                    $Val = false;
                    $ItemVal = $ItemVal . "(" . $Item->Id_Item . "- Cant. Min. C. =" . $ListaCostoDet->CantMinimaCompra . ") -";
                }

                if (fmod($MovDet->Cantidad, $ListaCostoDet->FactorCompra) != 0) {
                    $Val2 = false;
                    $ItemVal = $ItemVal . "(" . $Item->Id_Item . " no es divisor con el factor minimo de compra : " . $ListaCostoDet->FactorCompra . ")";
                }
            }
        }
        if ($Val == false) {
            funciones::Mensaje("Debes corregir la cantidad minima de compra de los siguientes items para poder autorizar el documento : " . $ItemVal . ",<br> o debes pedir segunda autorización por el concepto CANTIDADES MINIMAS DE COMPRA MENORES O NO MULTIPLOS", 2, $ObjVista);
            return false;
        } elseif ($Val2 == false) {
            funciones::Mensaje("Debes corregir la cantidad divisor de los siguientes items : " . $ItemVal . ",<br> o debes pedir segunda autorización por el concepto CANTIDADES MINIMAS DE COMPRA MENORES O NO MULTIPLOS", 2, $ObjVista);
            return false;
        } else {
            return true;
        }
    }

    public static function GenerarAlistamiento($IdMov) {
        $Mov = MovimientosRecord::finder()->FindByPk($IdMov);
        $Alistamiento = new AlistamientosRecord;
        $Alistamiento->FechaInicio = date('Y-m-d H:i:s');
        $Alistamiento->FechaTermina = date('Y-m-d H:i:s');
        $Alistamiento->IdMovimientoAlistamiento = $IdMov;
        $Alistamiento->IdAuxiliarAlistamientoAl = '26';
        $Alistamiento->Comentarios = 'Alistamiento automatico legalizaciones';
        $Alistamiento->UsuarioAdmin = 'kasten';
        $Alistamiento->IdPrioridadAlistamiento = 'ALTA';
        if ($Alistamiento->save()) {
            $Mov->Alistado = 1;
            $Mov->save();
            return true;
        } else {
            return false;
        }
    }

    /**
     * @Desc Retorna en true si elo movimiento es una legalizacion de remision o si el concepto de la factura es
     * @param <int> $Mov
     */
    public static function AplicaAlistamientoAut($Mov) {
        $Val = false;
        $Movimiento = new MovimientosRecord();
        $Movimiento = MovimientosRecord::finder()->FindByPk($Mov);
        $MovimientosDet = new MovimientosDetRecord();
        $Sql = "select movimientos_det.* from movimientos_det 
                LEFT JOIN movimientos_det as enlace on enlace.IdMovimientoDet = movimientos_det.Enlace
                where movimientos_det.IdDocumento = 3 and (enlace.IdDocumento = 11 OR enlace.IdDocumento = 12) and movimientos_det.IdMovimiento = " . $Movimiento->IdMovimiento . " limit 1";
        $MovimientosDet = MovimientosDetRecord::finder()->FindallBySql($Sql);
        if (($Movimiento->IdConcepto != '' && $Movimiento->IdConcepto == 171) || (is_array($MovimientosDet) && count($MovimientosDet) > 0)) {
            $Val = true;
        }
        return $Val;
    }

    public static function ObtenerListaCostosItem($IdItem) {
        $Datos = '';
        $Item = new ItemRecord();
        $Item = ItemRecord::finder()->FindByPk($IdItem);
        if (count($Item) > 0) {
            $ListaDet = new ListaCostosProvDetRecord();
            $ListaDet = ListaCostosProvDetRecord::finder()->FindByPk($Item->IdListaCostosDetItem);
            if (count($ListaDet) > 0) {
                $Datos = $ListaDet;
            }
        }
        return $Datos;
    }

    /**
     * Returna si verdadero si la bodega se puede utilizar
     * @param type $IdBodega
     * @return boolean
     * fh 27/02/2019
     */
    public static function ValidarBodega($IdBodega) {
        $Bod = new BodegasRecord();
        $Bod = BodegasRecord::finder()->FindByPk($IdBodega);
        $Val = false;
        if (count($Bod) > 0 && $Bod->Inactiva == 0) {
            $Val = true;
        } else {
            $Val = false;
        }
        return $Val;
    }

    public static function ValidarDescripcionI($Desc) {
        if (strlen($Desc) > 40) {
            $Array = str_split($Desc, 40);
        } else {
            $Array[] = $Desc;
        }
        return $Array;
    }

    public static function ValidarEstadosDetallesMov($IdMov, $ObjVista) {
        $MovDet = new MovimientosDetRecord();
        $Sql = "select * from movimientos_det  where Estado!='AUTORIZADO' and IdMovimiento = " . $IdMov;
        $MovDet = MovimientosDetRecord::finder()->FindAllBySql($Sql);
        if (count($MovDet) > 0) {
            funciones::Mensaje("En el documento hay productos con un estado diferente a autorizado, valide e intente de nuevo.", 2, $ObjVista);
            return false;
        } else {
            return true;
        }
    }

    public static function ValidarProductosKit($IdItemKit, $IdlistaPecios, $OpCot = false) {

        $ListaPDet = new ListaPreciosDetRecord();
        $ListaPDet = ListaPreciosDetRecord::finder()->FindBy_IdListaPrecios_and_Id_Item($IdlistaPecios, $IdItemKit);
        $KitsDet = null;
        if (count($ListaPDet) > 0 || $OpCot == true) {
            $KitsDet = null;
            $Kits = new KitsRecord();
            $Kits = KitsRecord::finder()->FindBy_Id_ItemKit_and_Autorizado($IdItemKit, 1);
            if (count($Kits) > 0) {
                $KitDet = new KitsDetRecord();
                $KitDet = KitsDetRecord::finder()->FindAllBy_IdKit($Kits->IdKit);
                foreach ($KitDet as $Det) {
                    $KitsDet[] = $Det->IdKitDet;
                }
            }
        }
        if (isset($KitDet) && $KitDet != null && $OpCot == false) {//Validamos que todos los kits esten en la lista de precios.
            foreach ($KitDet as $Row) {
                $KitD = new KitsDetRecord();
                $KitD = KitsDetRecord::finder()->FindByPk($Row);
                $ListaPDet = new ListaPreciosDetRecord();
                $ListaPDet = ListaPreciosDetRecord::finder()->FindBy_IdListaPrecios_and_Id_Item($IdlistaPecios, $KitD->Id_Item);
                if (count($ListaPDet) == 0) {
                    $KitsDet = false;
                }
            }
        }
        return $KitsDet;
    }

    public static function CrearDetallesKit($IdMov, $IdDoc, $Objvista = null) {
        try {
            $Afect = false;
            if ($IdDoc != 2 && $IdDoc != 100) {//Movimientos
                $Mov = new MovimientosRecord();
                $Mov = MovimientosRecord::finder()->FindByPk($IdMov);
                if ($Mov->Impresion == 0 && $Mov->Autorizado == 0) {
                    $Tercero = new TercerosRecord();
                    $Tercero = TercerosRecord::finder()->FindByPk($Mov->IdTercero);
                    $MovDet = new MovimientosDetRecord();
                    $MovDet = MovimientosDetRecord::finder()->FindAllBy_IdMovimiento_and_IdDocumento($Mov->IdMovimiento, $IdDoc);
                    if (count($MovDet) > 0) {
                        $idListaPrecios = funciones::DevListaPreciosTercero($Mov->IdTercero, $Mov->IdDireccion);
                        foreach ($MovDet as $Det) {
                            $Kist = funciones::ValidarProductosKit($Det->Id_Item, $idListaPrecios);
                            if (is_array($Kist) && count($Kist) > 0) {
                                $ValCant = count($Kist);
                                $KitVal = new KitsDetRecord();
                                $KitVal = KitsDetRecord::finder()->FindByPk($Kist[0]);
                                $KiVal = new KitsDetRecord();
                                $KiVal = KitsDetRecord::finder()->FindAllBy_IdKit($KitVal->IdKit);
                                if (count($KiVal) == $ValCant) {
                                    foreach ($Kist as $Kit) {
                                        $KitDet = new KitsDetRecord();
                                        $KitDet = KitsDetRecord::finder()->FindByPk($Kit);
                                        $Val = MovimientosDetRecord::finder()->FindBy_IdMovimiento_and_IdKit($Mov->IdMovimiento, $KitDet->IdKitDet);
                                        if (count($Val) <= 0) {
                                            $Item = new ItemRecord();
                                            $Item = ItemRecord::finder()->FindByPk($KitDet->Id_Item);
                                            $arDocumento = new DocumentosRecord();
                                            $arDocumento = DocumentosRecord::finder()->FindByPk($Det->IdDocumento);
                                            $MovimientosDet = new MovimientosDetRecord();
                                            $MovimientosDet->IdMovimiento = $Mov->IdMovimiento;
                                            $MovimientosDet->FechaDet = $Mov->Fecha;
                                            $MovimientosDet->IdDocumento = $Mov->IdDocumento;
                                            $MovimientosDet->TpDocumento = $Mov->TpDocumento;
                                            $MovimientosDet->NroDocumento = $Mov->NroDocumento;
                                            $MovimientosDet->Id_Item = $Item->Id_Item;
                                            $MovimientosDet->IdTercero = $Mov->IdTercero;
                                            $MovimientosDet->Cantidad = $KitDet->FactorKit * $Det->Cantidad;
                                            $MovimientosDet->PorIva = $Item->Por_Iva;
                                            $MovimientosDet->ExentoIVA = $Tercero->ExentoIva;
                                            $MovimientosDet->CostoPromedio = 0;
                                            $MovimientosDet->Estado = 'DIGITADO';
                                            $MovimientosDet->Operacion = $arDocumento->Operacion;
                                            $ListaPrecios = new ListaPreciosDetRecord;
                                            $ListaPrecios = ListaPreciosDetRecord::DevListaPreciosDet($Item->Id_Item, $Mov->IdTercero, $Mov->IdDireccion, $KitDet->IdKitDet);
                                            if (count($ListaPrecios) != 0) {
                                                $MovimientosDet->UM = $Item->UMM;
                                                if (Count($ListaPrecios) != 0) {
                                                    $MovimientosDet->Factor = $ListaPrecios->FactorVenta;
                                                    $MovimientosDet->Precio = $ListaPrecios->Precio;
                                                    $MovimientosDet->CantDescuento = $ListaPrecios->DctoListaPrecio;
                                                    $MovimientosDet->IdLista = $ListaPrecios->IdListaPreciosDet;
                                                } else
                                                    $MovimientosDet->Factor = 1;
                                            }
                                            else {
                                                $MovimientosDet->Factor = 1;
                                                $MovimientosDet->UM = $Item->UMM;
                                            }
                                            $MovimientosDet->CantFactor = $MovimientosDet->Cantidad;
                                            $MovimientosDet->IdKit = $KitDet->IdKitDet;
                                            $MovimientosDet->save();
                                            $Afect = true;
                                        }
                                    }
                                }
                            }
                            if ($Afect == true) {
                                MovimientosDetRecord::finder()->deleteByPk($Det->IdMovimientoDet);
                                $Afect = false;
                            }
                        }
                    }
                }
            } else if ($IdDoc == 2) {//Cotizaciones
                $Cot = new CotizacionesRecord();
                $Cot = CotizacionesRecord::finder()->FindByPk($IdMov);
                $DetCot = new CotizacionesDetRecord();
                $DetCot = CotizacionesDetRecord::finder()->FindAllBy_IdCotizacion($IdMov);
                $idListaPrecios = funciones::DevListaPreciosTercero($Cot->IdTerceroCotizacion, $Cot->IdDireccionCotizacion);
                foreach ($DetCot as $Det) {
                    if ($Det->IdItemCotizacion > 0) {
                        $Val = funciones::ValidarProductosKit($Det->IdItemCotizacion, $idListaPrecios, false);
                        $Kist = funciones::ValidarProductosKit($Det->IdItemCotizacion, $idListaPrecios, $Val == false ? true : false);
                        if (is_array($Kist) && count($Kist) > 0) {
                            foreach ($Kist as $Kit) {
                                $KitDet = new KitsDetRecord();
                                $KitDet = KitsDetRecord::finder()->FindByPk($Kit);
                                $KitDet = KitsDetRecord::finder()->FindByPk($Kit);
                                $Val = CotizacionesDetRecord::finder()->FindBy_IdCotizacion_and_IdKitCot($Cot->IdCotizacion, $KitDet->IdKitDet);
                                if (count($Val) <= 0) {
                                    $Item = new ItemRecord();
                                    $Item = ItemRecord::finder()->FindByPk($KitDet->Id_Item);
                                    $LisCosDet = new ListaCostosProvDetRecord();
                                    $LisCosDet = ListaCostosProvDetRecord::finder()->FindByPk($Item->IdListaCostosDetItem);
                                    $ItemExist = CotizacionesDetRecord::finder()->FindBy_IdCotizacion_and_IdKitCot($Cot->IdCotizacion, $KitDet->IdKitDet);
                                    if (count($ItemExist) <= 0) {
                                        $KitF = new KitsRecord();
                                    }
                                    $KitF = KitsRecord::finder()->FindByPk($KitDet->IdKit);
                                    $CotDet = new CotizacionesDetRecord();
                                    $CotDet->IdCotizacion = $IdMov;
                                    $CotDet->IdItemCotizacion = $Item->Id_Item;


                                    $CotDet->DescripcionCotizacion = $LisCosDet->DescripcionProv;
                                    $CotDet->FactorVCot = $LisCosDet->CantMinimaVenta;

                                    if ($LisCosDet->UMV == "") {
                                        if ($LisCosDet->Id_Item != "") {
                                            $Item = ItemRecord::finder()->FindByPk($LisCosDet->Id_Item);
                                            $CotDet->UMVCot = $Item->UMM;
                                        } else {
                                            $CotDet->UMVCot = $LisCosDet->UMC;
                                        }
                                    } else {
                                        $CotDet->UMVCot = $LisCosDet->UMV;
                                    }

                                    $CostoUMM = $LisCosDet->CostoUMM;
                                    $DatosLp = new ListaPreciosDetRecord();
                                    $DatosLp = ListaPreciosDetRecord::finder()->FindBy_IdListaPrecios_and_Id_Item($idListaPrecios, $KitF->Id_ItemKit);

                                    $CotDet->FhDesdeLista = $LisCosDet->FhDesde;
                                    $CotDet->FhHastaLista = $LisCosDet->FhHasta;

                                    if (count($DatosLp) > 0) {
                                        $CotDet->FhDesdePrecioCot = $DatosLp->FhDesde;
                                        $CotDet->FhHastaPrecioCot = $DatosLp->FhHasta;
                                    } else {
                                        $CotDet->FhDesdePrecioCot = $Cot->FechaDesde;
                                        $CotDet->FhHastaPrecioCot = $Cot->FechaHasta;
                                    }
                                    $CotDet->IdListaCostosDetCot = $LisCosDet->IdListaCostosProvDet;
                                    $Margen = funciones::MargenCot(1, $LisCosDet);
                                    $CotDet->Margen = $Margen;
                                    $CotDet->MargenOriginal = $Margen;
                                    $ListaPrecios = new ListaPreciosDetRecord;
                                    $ListaPrecios = ListaPreciosDetRecord::DevListaPreciosDet($Item->Id_Item, $Cot->IdTerceroCotizacion, $Cot->IdDireccionCotizacion, $KitDet->IdKitDet);
                                    $CotDet->PrecioCotizacion = $CostoUMM / (1 - ($Margen / 100));
                                    $CotDet->CostoCotizacion = $CostoUMM;
                                    $CotDet->Redondeo = 1;
                                    $CotDet->Consumo = $KitDet->FactorKit * $LisCosDet->FactorCompra;
                                    $CotDet->PorIvaCotizacion = $LisCosDet->IvaLC;
                                    $CotDet->CantidadCotizacion = $CotDet->Consumo;
                                    $CotDet->DescuentoFcieroCot = $LisCosDet->DescuentoFciero;
                                    $CotDet->ComentarioInterno = 'Cotizacion creada desde el cotizador.';
                                    $CotDet->IdKitCot = $KitDet->IdKitDet;
                                    $CotDet->save();
                                    $Afect = true;
                                }
                            }
                        }
                    }
                }
                if ($Afect == true) {
                    $KitsD = new KitsDetRecord();
                    $KitsD = KitsDetRecord::finder()->FindByPk($Kist[0]);
                    $KitI = new KitsRecord();
                    $KitI = KitsRecord::finder()->FindByPk($KitsD->IdKit);
                    $ValITeP = ListaPreciosDetRecord::DevListaPreciosDet($KitI->Id_ItemKit, $Cot->IdTerceroCotizacion, $Cot->IdDireccionCotizacion);
                    if (count($ValITeP) > 0) {
                        CotizacionesDetRecord::finder()->DeleteByPk($Det->IdCotizacionDet);
                    }
                    $Afect = false;
                }
            } else if ($IdDoc == 100) {//Cambio Precios Det
                $CambioP = new CambioPreciosRecord();
                $CambioP = CambioPreciosRecord::finder()->FindByPk($IdMov);
                $idListaPrecios = $CambioP->IdListaPreciosCP;
                $CambioPDet = new CambioPreciosDetRecord();
                $CambioPDet = CambioPreciosDetRecord::finder()->FindAllBy_IdCambioPrecio($IdMov);
                foreach ($CambioPDet as $Det) {
                    $Val = funciones::ValidarProductosKit($Det->Id_ItemCP, $idListaPrecios, false);
                    $Kist = funciones::ValidarProductosKit($Det->Id_ItemCP, $idListaPrecios, $Val == false ? true : false);
                    if (is_array($Kist) && count($Kist) > 0) {
                        foreach ($Kist as $Kit) {
                            $KitDet = new KitsDetRecord();
                            $KitDet = KitsDetRecord::finder()->FindByPk($Kit);
                            $Val = CambioPreciosDetRecord::finder()->FindBy_IdCambioPrecio_and_IdKit($CambioP->IdCambioPrecios, $KitDet->IdKitDet);
                            $ListaDet = ListaPreciosDetRecord::DevListaPreciosDetPorLista($KitDet->Id_Item, $idListaPrecios, $KitDet->IdKitDet);
                            if (count($Val) <= 0) {
                                if (CambioPreciosDetRecord::GuardarDetalle($CambioP->IdCambioPrecios, $KitDet->Id_Item, $CambioP->IdTerceroCP, '', '', $ListaDet->Precio, $Objvista->DtpFhDesdeLp->Text, $Objvista->DtpFhHastaLp->Text, $ListaDet->CodTercero, $ListaDet->DescripcionTercero, $ListaDet->IdListaCostosDet, $ListaDet->IdCotizacionDet, NULL, $KitDet->IdKitDet)) {
                                    $Afect = true;
                                }
                            }

                            if ($Afect == true) {
                                CambioPreciosDetRecord::finder()->DeleteByPk($Det->IdCambioPrecioDet);
                                $Afect = false;
                            }
                        }
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 
     * @param type $IdCambioPrecios
     * @param type $IdCambioPreciosDet
     * @param type $IdItem
     * @param type $Usr
     * @param type $Com
     * @return boolean
     */
    public static function CrearLogCambioP($IdCambioPrecios = '', $IdCambioPreciosDet = '', $IdItem, $Usr, $Com) {
        $Log = new LogCambioPreciosRecord();
        $Log->IdCambioPrecios = $IdCambioPrecios;
        $Log->IdCambioPreciosDet = $IdCambioPreciosDet;
        $Log->Id_Item = $IdItem;
        $Log->Fecha = date("Y-m-d H:i:s");
        $Log->Usuario = $Usr;
        $Log->Comentarios = $Com;
        if ($Log->save()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 
     * @param type $Accion
     * @param type $IdListaDet
     * @param type $IdItem
     * @param type $Usuario
     * @param type $PAnt
     * @param type $PNuevo
     * @param type $UMVAnt
     * @param type $UMVAct
     * @param type $FactorAnt
     * @param type $FactorAct
     */
    public static function CrearLogListaP($Accion, $IdListaDet, $IdItem, $Usuario, $PAnt = '', $PNuevo = '', $UMVAnt = '', $UMVAct = '', $FactorAnt = '', $FactorAct = '') {
        $Log = new LogListasRecord();
        $Log->Fecha = date('Y-m-d H:i:s');
        $Log->IdAccion = $Accion;
        $Log->IdDetalle = $IdListaDet;
        $Log->Id_Item = $IdItem;
        $Log->Usuario = $Usuario;
        $Log->Tipo = 1;
        $Log->PrecioAnt = $PAnt;
        $Log->PrecioAct = $PNuevo;
        $Log->UMVAnt = $UMVAnt;
        $Log->UMVAct = $UMVAct;
        $Log->FactorAnt = $FactorAnt;
        $Log->FactorAct = $FactorAct;
        $Log->save();
    }

    public static function CrearIndicadorTiempo($Usr, $IdMov, $IdDoc) {
        $Indic = new IndicadoresTiempoRecord();
        $Indic->Usuario = $Usr;
        $Indic->IdDocumento = $IdDoc;
        $Indic->IdMovimiento = $IdMov;
        $Indic->FhInicio = date("Y-m-d H:i:s");
        $Indic->save();
        return $Indic->Id;
    }

    public static function CerrarIndicadorTiempo($Id) {
        $Indic = new IndicadoresTiempoRecord();
        $Indic = IndicadoresTiempoRecord::finder()->FindByPK($Id);
        $Indic->FhFin = date("Y-m-d H:i:s");
        $Indic->save();
        return true;
    }

    public function FondosPlantilla($IdPlantilla) {
        $Plantilla = new PlantillasCorrespondenciaRecord();
        $Plantilla = PlantillasCorrespondenciaRecord::finder()->FindByPk($IdPlantilla);
        if ($Plantilla->Fondo != '') {
            return "/var/www/kasten/themes/Default/imagenes/FondosPlantillas/" . $Plantilla->Fondo;
        } else {
            return '';
        }
    }

    public static function GuardarCantAjustada($IdMov) {
        $MovDets = new MovimientosDetRecord();
        $Sql = "select * from movimientos_det where IdMovimiento=" . $IdMov;
        $MovDets = MovimientosDetRecord::finder()->with_Documento()->FindAllBySql($Sql);
        $Mov = new MovimientosRecord();
        $Mov = MovimientosRecord::finder()->with_Concepto()->FindByPk($IdMov);
        foreach ($MovDets as $Row) {
            $Item = new ItemRecord();
            $Item = ItemRecord::finder()->FindByPk($Row->Id_Item);
            $LCDet = new ListaCostosProvDetRecord();
            $LCDet = ListaCostosProvDetRecord::finder()->FindByPk($Item->IdListaCostosDetItem);
            if ($Row->TpDocumento == 5 && $Mov->IdConcepto > 0) {
                if ($Mov->Concepto->Opcion == 1) {
                    $Row->CantAjustada = ($Row->Cantidad / $LCDet->CantContenido) * $Row->Documento->OpValor;
                } else {
                    $Row->CantAjustada = $Row->Cantidad * $Row->Documento->OpValor;
                }
            } else {
                $Row->CantAjustada = $Row->Cantidad * $Row->Documento->OpValor;
            }
            $Row->save();
        }
        return true;
    }

    public static function ValidarProvisiones($IdItem, $IdTercero, $ObjVista) {

        $Movs = new MovimientosDetRecord();
        $Sql = "select IdMovimiento from movimientos_det where IdDocumento = 75 and Id_Item =" . $IdItem . " and IdTercero = " . $IdTercero . " and Estado='AUTORIZADO' and Confirmado =1";
        $Movs = MovimientosDetRecord::finder()->FindAllBySql($Sql);
        if (count($Movs) > 0) {
            funciones::Mensaje("El cliente cuenta con uno o varios pedido provision con IdMovimiento (" . $Movs[0]->IdMovimiento . ") reserva con el mismo producto " . $IdItem . ", realice la gestion correspondiente.", 2, $ObjVista);
            return false;
        } else {
            return true;
        }
    }

    public static function ValidarProdMedicamentoControl($IdItem, $IdTercero) {
        $Item = new ItemRecord();
        $Item = ItemRecord::finder()->FindByPk($IdItem);
        $ListaCostosDet = ListaCostosProvDetRecord::DevEnlaceRaiz($Item->IdListaCostosDetItem);
        $ListaCostos = ListaCostosProvDetRecord::finder()->FindByPK($ListaCostosDet);
        if (count($ListaCostos) > 0) {
            if ($ListaCostos->MedControl == 1) {
                $MedControlTercero = new TercerosMedControlRecord();
                $MedControlTercero = TercerosMedControlRecord::finder()->FindByPk($IdTercero, $IdItem);
                if (count($MedControlTercero) > 0) {
                    if ($MedControlTercero->FhHasta >= date("Y-m-d")) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    public static function RecuperarDatosTrabajo($ObjVista) {
        $DatosTrabajo = new DatosTrabajoMovimientosRecord();
        $DatosTrabajo = DatosTrabajoMovimientosRecord::finder()->FindByPk($ObjVista->User->Name);
        if (count($DatosTrabajo) <= 0) {
            $DatosTrabajo = new DatosTrabajoMovimientosRecord();
            $DatosTrabajo->Usuario = $ObjVista->User->Name;
            $DatosTrabajo->save();
            $DatosTrabajo = DatosTrabajoMovimientosRecord::finder()->FindByPk($ObjVista->User->Name);
        }
        if ($DatosTrabajo->IdMovimiento != $ObjVista->Request["IdMov"]) {
            $DatosTrabajo->Id_Item = null;
            $DatosTrabajo->Descripcion = null;
            $DatosTrabajo->Marca = null;
            $DatosTrabajo->Estado = null;
            $DatosTrabajo->IdTercero = null;
            $DatosTrabajo->Referencia = null;
            $DatosTrabajo->CodProveedor = null;
            $DatosTrabajo->CampoExt1 = null;
            $DatosTrabajo->CampoExt2 = null;
            $DatosTrabajo->IdMovimiento = null;
            $DatosTrabajo->save();
        }
        $Marcar = false;
        if (count($DatosTrabajo) > 0) {

            $ObjVista->IdItemDoc->Text = $DatosTrabajo->Id_Item;
            $ObjVista->setViewState('IdItem', $DatosTrabajo->Id_Item);
            if ($ObjVista->IdItemDoc->Text != "") {
                $Marcar = true;
                $ObjVista->IdItemDoc->BorderStyle = "solid";
                $ObjVista->IdItemDoc->BorderColor = "red";
                $ObjVista->IdItemDoc->ToolTip = 'Item contiene un filtro';
            } else {
                $ObjVista->IdItemDoc->BorderStyle = "";
                $ObjVista->IdItemDoc->BorderColor = "";
                $ObjVista->IdItemDoc->ToolTip = '';
            }

            $ObjVista->DescripcionDoc->Text = $DatosTrabajo->Descripcion;
            $ObjVista->setViewState('Descripcion', $DatosTrabajo->Descripcion);
            if ($ObjVista->DescripcionDoc->Text != "") {
                $Marcar = true;
                $ObjVista->DescripcionDoc->BorderStyle = "solid";
                $ObjVista->DescripcionDoc->BorderColor = "red";
                $ObjVista->DescripcionDoc->ToolTip = 'Item contiene un filtro';
            } else {
                $ObjVista->DescripcionDoc->BorderStyle = "";
                $ObjVista->DescripcionDoc->BorderColor = "";
                $ObjVista->DescripcionDoc->ToolTip = '';
            }

            $ObjVista->ReferenciaDoc->Text = $DatosTrabajo->Referencia;
            $ObjVista->setViewState('Referencia', $DatosTrabajo->Referencia);
            if ($ObjVista->ReferenciaDoc->Text != "") {
                $Marcar = true;
                $ObjVista->ReferenciaDoc->BorderStyle = "solid";
                $ObjVista->ReferenciaDoc->BorderColor = "red";
                $ObjVista->ReferenciaDoc->ToolTip = 'Item contiene un filtro';
            } else {
                $ObjVista->ReferenciaDoc->BorderStyle = "";
                $ObjVista->ReferenciaDoc->BorderColor = "";
                $ObjVista->ReferenciaDoc->ToolTip = '';
            }

            $ObjVista->TxtIdTerceroFl->Text = $DatosTrabajo->IdTercero;
            $ObjVista->setViewState('Cliente', $DatosTrabajo->IdTercero);
            if ($ObjVista->TxtIdTerceroFl->Text != "") {
                $Marcar = true;
                $ObjVista->TxtIdTerceroFl->BorderStyle = "solid";
                $ObjVista->TxtIdTerceroFl->BorderColor = "red";
                $ObjVista->TxtIdTerceroFl->ToolTip = 'Item contiene un filtro';
            } else {
                $ObjVista->TxtIdTerceroFl->BorderStyle = "";
                $ObjVista->TxtIdTerceroFl->BorderColor = "";
                $ObjVista->TxtIdTerceroFl->ToolTip = '';
            }

            $ObjVista->Cbo_Estado->SelectedValue = $DatosTrabajo->Estado;
            $ObjVista->setViewState('Estado', $DatosTrabajo->Estado);
            if ($ObjVista->Cbo_Estado->SelectedValue != "") {
                $Marcar = true;
                $ObjVista->Cbo_Estado->BorderStyle = "solid";
                $ObjVista->Cbo_Estado->BorderColor = "red";
                $ObjVista->Cbo_Estado->ToolTip = 'Item contiene un filtro';
            } else {
                $ObjVista->Cbo_Estado->BorderStyle = "";
                $ObjVista->Cbo_Estado->BorderColor = "";
                $ObjVista->Cbo_Estado->ToolTip = '';
            }

            $ObjVista->Cbo_MarcaFl->SelectedValue = $DatosTrabajo->Marca;
            $ObjVista->setViewState('Marca', $DatosTrabajo->Marca);
            if ($ObjVista->Cbo_MarcaFl->SelectedValue != "") {
                $Marcar = true;
                $ObjVista->Cbo_MarcaFl->BorderStyle = "solid";
                $ObjVista->Cbo_MarcaFl->BorderColor = "red";
                $ObjVista->Cbo_MarcaFl->ToolTip = 'Item contiene un filtro';
            } else {
                $ObjVista->Cbo_MarcaFl->BorderStyle = "";
                $ObjVista->Cbo_MarcaFl->BorderColor = "";
                $ObjVista->Cbo_MarcaFl->ToolTip = '';
            }

            $ObjVista->CodProvDoc->Text = $DatosTrabajo->CodProveedor;
            $ObjVista->setViewState('CodProveedor', $DatosTrabajo->CodProveedor);
            if ($ObjVista->CodProvDoc->Text != "") {
                $Marcar = true;
                $ObjVista->CodProvDoc->BorderStyle = "solid";
                $ObjVista->CodProvDoc->BorderColor = "red";
                $ObjVista->CodProvDoc->ToolTip = 'Item contiene un filtro';
            } else {
                $ObjVista->CodProvDoc->BorderStyle = "";
                $ObjVista->CodProvDoc->BorderColor = "";
                $ObjVista->CodProvDoc->ToolTip = '';
            }

            if ($Marcar == true) {
                $ObjVista->IBtnFiltro->BorderStyle = "solid";
                $ObjVista->IBtnFiltro->BorderColor = "red";
                $ObjVista->IBtnFiltro->BorderWidth = 2;
                $ObjVista->IBtnFiltro->ToolTip = "El documento contiene un filtro";
            } else {
                $ObjVista->IBtnFiltro->BorderWidth = 0;
            }
        }
    }

    public static function GuardarDatosFiltro($ObjVista) {
        $DatosTrabajo = new DatosTrabajoMovimientosRecord();
        $DatosTrabajo = DatosTrabajoMovimientosRecord::finder()->FindByPk($ObjVista->User->Name);

        if (count($DatosTrabajo) <= 0) {
            $DatosTrabajo = new DatosTrabajoMovimientosRecord();
            $DatosTrabajo->Usuario = $ObjVista->User->Name;
        }
        if (isset($ObjVista->Request["IdMov"])) {
            $DatosTrabajo->IdMovimiento = $ObjVista->Request["IdMov"];
        }
        if ($ObjVista->IdItemDoc->Text != "") {
            $DatosTrabajo->Id_Item = $ObjVista->IdItemDoc->Text;
        } else {
            $DatosTrabajo->Id_Item = null;
        }

        if ($ObjVista->DescripcionDoc->Text != "") {
            $DatosTrabajo->Descripcion = $ObjVista->DescripcionDoc->Text;
        } else {
            $DatosTrabajo->Descripcion = null;
        }

        if ($ObjVista->ReferenciaDoc->Text != "") {
            $DatosTrabajo->Referencia = $ObjVista->ReferenciaDoc->Text;
        } else {
            $DatosTrabajo->Referencia = null;
        }

        if ($ObjVista->TxtIdTerceroFl->Text != "") {
            $DatosTrabajo->IdTercero = $ObjVista->TxtIdTerceroFl->Text;
        } else {
            $DatosTrabajo->IdTercero = null;
        }


        if ($ObjVista->Cbo_MarcaFl->SelectedValue != "") {
            $DatosTrabajo->Marca = $ObjVista->Cbo_MarcaFl->SelectedValue;
        } else {
            $DatosTrabajo->Marca = null;
        }

        if ($ObjVista->CodProvDoc->Text != "") {
            $DatosTrabajo->CodProveedor = $ObjVista->CodProvDoc->Text;
        } else {
            $DatosTrabajo->CodProveedor = null;
        }

        if ($ObjVista->Cbo_Estado->SelectedValue != "") {
            $DatosTrabajo->Estado = $ObjVista->Cbo_Estado->SelectedValue;
        } else {
            $DatosTrabajo->Estado = '';
        }

        $DatosTrabajo->save();
    }

    public static function ValidarReservas($IdMovDet = '') {
        $Reserva = new ReservasRecord();
        $Reserva = ReservasRecord::finder()->FindBy_IdMovimientoDetRes($IdMovDet);
        return count($Reserva);
    }

    public static function AsignarConsecutivoRadicado($IdDoc) {
        $DocumentosRecord = new DocumentosRecord;
        $DocumentosRecord = DocumentosRecord::finder()->findByPk($IdDoc);
        $Consecutivo = $DocumentosRecord->Consecutivo;
        $DocumentosRecord->Consecutivo = $DocumentosRecord->Consecutivo + 1;
        $DocumentosRecord->save();
        return $Consecutivo . date("Ymd:I");
    }

    public static function DevMovOrigen($IdMovDet, $IdDocFin = 3) {
        $IdOrigen = '';
        $IdActual = $IdMovDet;
        $Cont = 10;
        $i = 0;
        while ($i <= $Cont) {
            if ($IdActual > 0) {
                $MovDet = new MovimientosDetRecord();
                $MovDet = MovimientosDetRecord::finder()->FindByPk($IdActual);
                if ($IdDocFin == $MovDet->IdDocumento) {
                    return $MovDet;
                    break;
                }
                $IdActual = $MovDet->Enlace;
            } else {
                return $MovDet;
                break;
            }
        }
    }

    /**
     * 
     * @param type $Fecha1 La fecha aactual
     * @param type $Fecha2 La fecha a comparar
     * @return type
     */
    public static function ObtenerStringFechaDif($Fecha1, $Fecha2) {
        $date1 = new DateTime(($Fecha2));
        $date2 = new DateTime($Fecha1);

        $diff = $date1->diff($date2);
        $str = '';
        $str .= ($diff->invert == 1) ? ' - ' : '';
        if ($diff->y > 0) {
            // years
            $str .= ($diff->y > 1) ? $diff->y . ' Años ' : $diff->y . ' Año ';
        } if ($diff->m > 0) {
            // month
            $str .= ($diff->m > 1) ? $diff->m . ' Meses ' : $diff->m . ' Mes ';
        } if ($diff->d > 0) {
            // days
            $str .= ($diff->d > 1) ? $diff->d . ' Dias ' : $diff->d . ' Dia ';
        } if ($diff->h > 0) {
            // hours
            $str .= ($diff->h > 1) ? $diff->h . ' Horas ' : $diff->h . ' Hora ';
        } if ($diff->i > 0) {
            // minutes
            $str .= ($diff->i > 1) ? $diff->i . ' Minutos ' : $diff->i . ' Minuto ';
        } if ($diff->s > 0) {
            // seconds
            $str .= ($diff->s > 1) ? $diff->s . ' Segundos ' : $diff->s . ' Segundo ';
        }
        return ['string' => $str, 'dias' => $diff->d];
    }

    public static function ValidarEscalasPreciosMov($IdMovimiento) {
        $Mov = MovimientosRecord::finder()->FindByPk($IdMovimiento);
        $MovDets = new MovimientosDetRecord();
        $MovDets = MovimientosDetRecord::finder()->FindAllBy_IdMovimiento($IdMovimiento);
        if ($Mov->IdDocumento == 8) {
            foreach ($MovDets as $Det) {
                if ($Det->IdEscala > 0) {
                    funciones::ValidarEscalaMov($Det->IdMovimientoDet);
                }
                $Escala = funciones::EscalaPrecios($Det->IdMovimientoDet);
                if (count($Escala) > 0 && $Det->IdEscala == 0) {
                    $Det->CantDescuento = $Escala->Descuento;
                    $Det->IdEscala = $Escala->IdEscala;
                    $Det->Comentarios = "Se aplico precio escala" . $Escala->IdEscala;
                    $Det->save();
                }
            }
        }
    }

    /**
     * 
     * @param type $IdMovDet
     * @return type
     * Valida los detalles de un pedido para asignar escala.
     */
    public static function EscalaPrecios($IdMovDet) {
        $MovDet = new MovimientosDetRecord();
        $MovDet = MovimientosDetRecord::finder()->with_Movimiento()->FindByPk($IdMovDet);
        $Escala = new EscalasPreciosRecord();
        $Sql = "select * from escalas_precios where "
                . "Id_Item = " . $MovDet->Id_Item . " and IdTercero = " . $MovDet->IdTercero . " "
                . "and (IdDireccion =" . $MovDet->Movimiento->IdDireccion . " or IdDireccion =0) "
                . "and (FhDesde <=curdate() and FhHasta >= curdate()) "
                . "and (" . $MovDet->Cantidad . " >= CantInicial and " . $MovDet->Cantidad . " <= CantMaxima) "
                . "and Inactiva =0 order by IdDireccion DESC limit 1";
        $Escala = EscalasPreciosRecord::finder()->FindBySql($Sql);
        if (count($Escala) <= 0) {
            $Escala = new EscalasPreciosRecord();
            $Sql = "select * from escalas_precios where "
                    . "Id_Item = " . $MovDet->Id_Item . " and IdTercero = 800158193 "
                    . "and (FhDesde <=curdate() and FhHasta >= curdate()) "
                    . "and (" . $MovDet->Cantidad . " >= CantInicial and " . $MovDet->Cantidad . " <= CantMaxima) "
                    . "and Inactiva =0 order by IdDireccion DESC limit 1";
            $Escala = EscalasPreciosRecord::finder()->FindBySql($Sql);
        }
        if (count($Escala) > 0) {
            return $Escala;
        } else {
            return [];
        }
    }

    /**
     * 
     * @param type $IdMovDet
     * Valida si algun detalle de un pedido que tenga escala, si las condiciones cambiaron elimine el descuento y la escala
     */
    public static function ValidarEscalaMov($IdMovDet) {
        $MovDetC = new MovimientosDetRecord();
        $MovDetC = MovimientosDetRecord::finder()->FindByPk($IdMovDet);

        $MovDet = new MovimientosDetRecord();
        $Sql = "select movimientos_det.*, sum(Cantidad) as Cantidad from movimientos_det where IdMovimiento = " . $MovDetC->IdMovimiento . " and Id_Item =" . $MovDetC->Id_Item;
        $MovDet = MovimientosDetRecord::finder()->FindBySql($Sql);

        $Escala = new EscalasPreciosRecord();
        $Escala = EscalasPreciosRecord::finder()->findByPk($MovDetC->IdEscala);
        if ($Escala->Inactiva == 0) {
            if ($MovDet->Cantidad < $Escala->CantInicial) {
                $MovDetC->IdEscala = 0;
                $MovDetC->Comentarios = '';
                $MovDetC->CantDescuento = 0;
                $MovDetC->save();
            } else if ($MovDet->Cantidad > $Escala->CantMaxima) {
                $MovDetC->IdEscala = 0;
                $MovDetC->Comentarios = '';
                $MovDetC->CantDescuento = 0;
                $MovDetC->save();
            } else if ($Escala->FhHasta < date("Y-m-d")) {
                $MovDetC->IdEscala = 0;
                $MovDetC->Comentarios = '';
                $MovDetC->CantDescuento = 0;
                $MovDetC->save();
            }
        } else {
            $MovDetC->IdEscala = 0;
            $MovDetC->Comentarios = '';
            $MovDetC->CantDescuento = 0;
            $MovDetC->save();
        }
    }

    /**
     * 
     * @param type $IdAccion
     * @param type $IdMovimiento
     * @param type $IdDocumento
     * @param type $ObjVista
     * @param type $Comentarios
     * @param type $IdMovimientoDet
     * @param type $IdItem
     * @return boolean
     */
    public static function CrearLogDoc($IdAccion, $IdMovimiento, $IdDocumento, $ObjVista, $Comentarios = '', $IdMovimientoDet = null, $IdItem = null) {
        try {
            $LogNew = new LogDocsRecord();
            $LogNew->IdAccion = $IdAccion;
            $LogNew->Id_Item = $IdItem;
            $LogNew->IdDocumento = $IdDocumento;
            $LogNew->IdMovimiento = $IdMovimiento;
            $LogNew->IdMovimientoDet = $IdMovimientoDet;
            $LogNew->Comentarios = $Comentarios;
            $LogNew->Fecha = date("Y-m-d H:i:s");
            $LogNew->Usuario = $ObjVista->User->Name;
            $LogNew->IdDespacho = '';
            $LogNew->save();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function ObtenerNumerosString($String) {
        $NumReg = strlen($String);
        $StringDev = '';
        $String = str_split($String);
        foreach ($String as $row) {
            if (is_numeric($row)) {
                $StringDev .= $row;
            }
        }
        return $StringDev;
    }

    /**
     * 
     * @param type $IdMov
     * @param type $IsMovDet
     * @param type $objVista
     * @return boolean
     */
    public function ValidarDisponiblesLote($IdMov, $IsMovDet = false, $objVista) {
        $Mov = new MovimientosDetRecord();
        $Movimiento = new MovimientosRecord();
        if (!$IsMovDet) {
            $Mov = MovimientosDetRecord::finder()->with_Documento()->FindAllBy_IdMovimiento($IdMov);
        } else {
            $Mov = MovimientosDetRecord::finder()->FindByPk($IdMov);
        }
        $Valid = true;
        if (count($Mov) > 0) {
            if (!$IsMovDet) {
                $Movimiento = MovimientosRecord::finder()->with_Concepto()->with_DocumentoMov()->FindByPk($Mov[0]->IdMovimiento);
                if (($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion == 0) || ($Movimiento->DocumentoMov->AfectaInventarios == 1 && $Movimiento->IdConcepto == null)) {
                    foreach ($Mov as $Det) {
                        if ($Det->Estado == 'DIGITADO') {
                            if ($Det->Lote != '' && $Det->Bodega > 0) {
                                if (funciones::RegenarReservas($Det->Id_Item, $Det->Lote, $objVista) == false) {
                                    return false;
                                }
//                                if (!funciones::RegenerarRemisiones($Det->Id_Item, $Det->Lote, $objVista)) {
//                                    return false;
//                                }
                                funcionesinventario::RegenerarKardex($Det->Id_Item, false);
                                $Lote = new LotesRecord();
                                $Lote = LotesRecord::finder()->FindByPk($Det->Id_Item, $Det->Lote, $Det->Bodega);
                                if (count($Lote) > 0 && $Movimiento->DocumentoMov->Operacion < 0) {
                                    $Cantidad = ($Det->Cantidad + $Det->CantOperada);
                                    if ($Lote->Disponible < $Cantidad) {
                                        funciones::Mensaje("Error, el item " . $Det->Id_Item . " con lote " . $Det->Lote . " no tiene disponible para sacar " . $Cantidad . ", la cantidad disponible es :" . $Lote->Disponible, 2, $objVista);
                                        $Valid = false;
                                        return false;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $Det = $Mov;
                $Movimiento = MovimientosRecord::finder()->with_Concepto()->with_DocumentoMov()->FindByPk($Det->IdMovimiento);
                if (($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion == 0) || ($Movimiento->DocumentoMov->AfectaInventarios == 1 && $Movimiento->IdConcepto == null)) {
                    if ($Det->Estado == 'DIGITADO') {
                        if ($Det->Lote != '' && $Det->Bodega > 0) {
                            if (funciones::RegenarReservas($Det->Id_Item, $Det->Lote, $objVista) == false) {
                                return false;
                            }
//                            if (!funciones::RegenerarRemisiones($Det->Id_Item, $Det->Lote, $objVista)) {
//                                return false;
//                            }
                            funcionesinventario::RegenerarKardex($Det->Id_Item, false);
                            $Lote = new LotesRecord();
                            $Lote = LotesRecord::finder()->FindByPk($Det->Id_Item, $Det->Lote, $Det->Bodega);
                            if (count($Lote) > 0 && $Movimiento->DocumentoMov->Operacion < 0) {
                                $Cantidad = ($Det->Cantidad + $Det->CantOperada);
                                if ($Lote->Disponible < $Cantidad) {
                                    funciones::Mensaje("Error, el item " . $Det->Id_Item . " con lote " . $Det->Lote . " no tiene disponible para sacar " . $Cantidad . ", la cantidad disponible es :" . $Lote->Disponible, 2, $objVista);
                                    $Valid = false;
                                    return false;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $Valid;
    }

    public static function RegenarReservas($IdItem, $Lote, $objVista) {
        set_time_limit(0);

        try {
            $sql = "UPDATE lotes SET Reserva=0 where Id_Item=" . $IdItem;
            $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
            $command->execute();

            $sql = "UPDATE item SET Reserva=0 where Id_Item=" . $IdItem;
            $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
            $command->execute();
            $rsItems = ItemRecord::finder()->FindAllBy_Id_Item_And_AfectaInventario($IdItem, 1);

            $j = 0;
            while ($j < count($rsItems)) {

                $sql = "SELECT reservas.*
                  FROM reservas
                  WHERE Id_ItemRes=" . $rsItems[$j]->Id_Item . " AND CantidadRes!=0";

                $Reservas = ReservasRecord::finder()->findAllBySql($sql);
                $i = 0;
                $s = 0;
                $ReservadaT = 0;
                while ($i < count($Reservas)) {
                    $Item = $Reservas[$i]->Id_ItemRes;
                    $Lote = $Reservas[$i]->LoteRes;
                    $Bodega = $Reservas[$i]->BodegaRes;
                    $Cantidad = $Reservas[$i]->CantidadRes;
                    $ReservadaT = $ReservadaT + $Cantidad;

                    $Lotes = LotesRecord::finder()->findByPk($Item, $Lote, $Bodega);
                    if (count($Lotes) <= 0) {
                        echo "Error fatal no existe lote con reserva del item=" . $Item . " Lote=" . $Lote . " Bodega=" . $Bodega;
                        return false;
                    }

                    $sql = "UPDATE lotes set Reserva=Reserva+" . $Cantidad . ", Disponible=Existencia-(Remisionada+Reserva) WHERE Id_Item=" . $Item . " and Lote='" . $Lote . "' and Bodega=" . $Bodega;
                    $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
                    $command->execute();
                    $i++;
                }
                $sql = "UPDATE item SET Reserva=" . $ReservadaT . ", Disponible=Existencia-(Remisionada+Reserva)  WHERE Id_Item=" . $rsItems[$j]->Id_Item;
                $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();
                $j++;
            }
            return true;
        } catch (Exception $e) {
            funciones::Mensaje("Ocurrio un error al regenerar las reservas del item " . $IdItem . '' . $e, 2, $objVista);
            return false;
        }
    }

    public static function RegenerarRemisiones($IdItem, $Lote, $objVista) {
        set_time_limit(0);
        try {
            $sql = "UPDATE lotes SET Remisionada=0 where Id_Item=" . $IdItem;
            $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
            $command->execute();

            $sql = "UPDATE item SET Remisionada=0 where Id_Item=" . $IdItem;
            $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
            $command->execute();
            $rsItems = ItemRecord::finder()->FindAllBy_Id_Item_And_AfectaInventario($IdItem, 1);

            $j = 0;
            while ($j < count($rsItems)) {
                //$sql="Select movimientos_det.* from movimientos_det where (movimientos_det.Estado='AUTORIZADO' OR movimientos_det.Estado='CERRADO') and movimientos_det.Operacion <> 0 and movimientos_det.TpDocumento<>7 and movimientos_det.Id_Item=".$rsItems[$j]->Id_Item." and FechaDet>'".$FechaCierre."' Order By FechaDet, IdMovimientoDet";
                $sql = "SELECT movimientos_det.*, (movimientos_det.Cantidad-movimientos_det.CantAfectada) as Pendiente
    	       FROM movimientos_det
    	       WHERE movimientos_det.Id_Item=" . $rsItems[$j]->Id_Item . "  AND movimientos_det.IdDocumento=11 AND (Cantidad - CantAfectada)!=0 AND (movimientos_det.Operacion!=0) AND movimientos_det.Estado='AUTORIZADO'";

                $MovDet = MovimientosDetRecord::finder('MovimientosDetExtRecord')->findAllBySql($sql);
                $i = 0;
                $s = 0;
                $RemisionadaT = 0;
                while ($i < count($MovDet)) {
                    $Item = $MovDet[$i]->Id_Item;
                    $Lote = $MovDet[$i]->Lote;
                    $Bodega = $MovDet[$i]->Bodega;
                    $FhVencimiento = $MovDet[$i]->FhVencimiento;
                    $Cantidad = $MovDet[$i]->Pendiente;
                    $RemisionadaT = $RemisionadaT + $Cantidad;

                    $Lotes = LotesRecord::finder()->findByPk($Item, $Lote, $Bodega);
                    if (count($Lotes) <= 0) {
                        echo "Error fatal no existe el lote del item=" . $Item . " Lote=" . $Lote . " Bodega=" . $Bodega;
                        return false;
                    }

                    if ($Cantidad >= 0) {
                        $sql = "UPDATE lotes set Remisionada=Remisionada+" . $Cantidad . ", Disponible=Existencia-(Remisionada+Reserva) WHERE Id_Item=" . $Item . " and Lote='" . $Lote . "' and Bodega=" . $Bodega;
                        $command = LotesRecord::finder()->getDbConnection()->createCommand($sql);
                        $command->execute();
                        $i++;
                    } else {
                        funciones::Mensaje("Error la cantidad $Cantidad  remisionada del item" . $Item . "  Lote=" . $Lote . " y Bodega=" . $Bodega . " no puede ser negativa", 2, $objVista);
                        break;
                        return false;
                    }
                }
                $sql = "UPDATE item SET Remisionada=" . $RemisionadaT . ", Disponible=Existencia-(Remisionada+Reserva)  WHERE Id_Item=" . $rsItems[$j]->Id_Item;
                $command = ItemRecord::finder()->getDbConnection()->createCommand($sql);
                $command->execute();
                $j++;
            }
            return true;
        } catch (Exception $e) {
            funciones::Mensaje("Ocurrio un error al regenerar las remisiones del item " . $IdItem . '' . $e->getMessage(), 2, $objVista);
            return false;
        }
    }

    public static function ValidarLotesItems($MovDet, $ObjVista) {
        $BoolVal = true;
        if (($MovDet->IdDocumento == 3 || $MovDet->IdDocumento == 11) && $MovDet->Estado == "DIGITADO") {
            if ($MovDet->Lote != '' && $MovDet->Bodega != 0) {
                if (funciones::ValidarSegundaAutorizacion(44, $MovDet->IdMovimiento, true, $ObjVista) == false) {
                    $Lote = new LotesRecord();
                    $Sql = "SELECT bodegas.NmBodega, bodegas.IdBodega, lotes.*
                            FROM `bodegas`, lotes, item
                            WHERE (lotes.Id_Item=" . $MovDet->Id_Item . ") AND (bodegas.IdBodega=lotes.Bodega) AND (item.Id_Item=lotes.Id_Item) and Bodega =1 and lotes.Lote != '" . $MovDet->Lote . "' and FhVencimiento < '" . $MovDet->FhVencimiento . "' and FhVencimiento >= CURDATE() and lotes.Disponible >0 ORDER BY FhVencimiento ASC limit 1";
                    $Lote = LotesRecord::finder('LotesExtRecord')->findAllBySql($Sql);
                    if (count($Lote) > 0) {
                        funciones::Mensaje("El item " . $MovDet->Id_Item . " con lote " . $MovDet->Lote . ", tiene otros lotes con fechas menores, valida de nuevo o solicita segunda autorizacion FACTURAR LOTES CON FECHAS MAYORES", 2, $ObjVista);
                        $BoolVal = false;
                    }
                }
            }
        }
        return $BoolVal;
    }

    public static function TiposPlantillas() {
        $Tipos = array(
            'GENERAL' => 'GENERAL',
            'ESPECIAL' => 'ESPECIAL'
        );
        return $Tipos;
    }

    public static function ValidarSoporteMovimiento($IdDoc, $Soporte, $IdMov = null) {
        $StrMensaje = null;
        if ($IdDoc > 0 && $Soporte != '') {
            $Movs = new MovimientosRecord();
            $Soporte = str_replace(" ", "", $Soporte);
            $Soporte = strtolower($Soporte);
            $Sql = "select movimientos.* from movimientos where IdDocumento = " . $IdDoc . " and Estado <> 'ANULADA' and LOWER(LTRIM(Soporte)) like '%" . $Soporte . "%'";
            if ($IdMov != null) {
                $Sql .= " and movimientos.IdMovimiento <> " . $IdMov;
            }
            $Movs = MovimientosRecord::finder()->FindAllBySql($Sql);
            if (count($Movs) > 0) {
                $StrMensaje = "Los siguientes movimientos tienen el mismo Soporte/Factura relacionada :<br>";
                foreach ($Movs as $Mov) {
                    $StrMensaje .= " [ IdMov=>" . $Mov->IdMovimiento . "]<br>";
                }
            }
        }
        return $StrMensaje;
    }

    public static function conceptosNotacCreditoProv($idConcepto) {
        $arrConceptos = [131, 177, 178, 179, 180, 183, 185];
        return in_array($idConcepto, $arrConceptos);
    }

    public static function RegistrarAfectadaPerf($IdMovimiento, $Tipo, $objVista, $RegistrarAfectada, $IsMovDet = false, $Op = true) {
        try{
            $TipoMov = "IdMovimiento";
            if ($IsMovDet == true)
                $TipoMov = "IdMovimientoDet";

            $MovDet = new MovimientosDetRecord();
            $sql = "Select * from movimientos_det where $TipoMov=" . $IdMovimiento;
            $MovDet = MovimientosDetRecord::finder()->FindAllBySql($sql);
            $Afectada = false;
            $Mensaje = '';
            if (count($MovDet) > 0) {
                switch ($Tipo) {
                    case 1:
                        funciones::registrarAfectadaAut($IdMovimiento, $objVista, $RegistrarAfectada, $Op, $MovDet);
                        break;

                    case 2:
                        funciones::registrarAfectadaDesAut($IdMovimiento, $objVista, $RegistrarAfectada, $Op, $MovDet);
                        break;
                }
            }
            return true;
        }
        catch(Exception $e){
            return false;
        }

        /*if (count($MovDet) > 0) {

            if ($Tipo == 1) {

                foreach ($MovDet as $MovDet) {
                    if ($Op == true && $MovDet->Estado != "AUTORIZADO" || $Op == false) {//Valida para que no vuelva a registrar un detalle que ya se autorizo.
                        $MovDetDatos = new MovimientosDetRecord();
                        $MovDetDatos = MovimientosDetRecord::finder()->with_MovDet()->FindByPk($MovDet->IdMovimientoDet);
                        $Movimiento = new MovimientosRecord();
                        $Movimiento = MovimientosRecord::finder()->with_Concepto()->with_DocumentoMov()->FindByPK($MovDet->IdMovimiento);
                        if ((($MovDetDatos->IdDocumento == 34 || $MovDetDatos->IdDocumento == 72) && $MovDetDatos->Operacion <> 0) || funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto) || ($MovDetDatos->MovDet && $MovDetDatos->MovDet->IdDocumento == 75) || $RegistrarAfectada == false || $MovDetDatos->IdDocumento == 10) {
                            $Afectada = true;
                            $MovDetEnlace = null;
                            if ($Movimiento->IdConcepto == 179 && $Movimiento->IdDocumento == 87) {
                                $Cantidad = $MovDetDatos->Cantidad;
                                $MovDetEnlace = new MovimientosDetRecord();
                                $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                                if ($Movimiento->IdConcepto > 0) {
                                    $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $Cantidad;
                                    //Validamos las notas credito proveedor que afecte cantidad como costo.
                                    if ($MovDetEnlace->IdDocumento == 85) {
                                        $DifCostoRec = ($MovDetDatos->CantidadReconocidaNC * $MovDetDatos->DiferenciaCostoNC);
                                        $DifSolicitada = ($MovDetDatos->CantSolicitud * $MovDetDatos->Costo);
                                        $Diferencia = $DifCostoRec - $DifSolicitada;
                                        if ($Diferencia >= -2000 && $Diferencia <= 2000 && $MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                                            $MovDetEnlace->Estado = 'CERRADO';
                                        }
                                    } else if ($MovDetEnlace->CantAfectada <= $MovDetEnlace->Cantidad) {
                                        $MovDetEnlace->Estado = 'CERRADO';
                                    }
                                    $MovDetEnlace->Comentarios = $MovDetDatos->Comentarios;
                                    $MovDetEnlace->save();
                                }
                            } else if (funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto) && $Movimiento->DocumentoMov->AfectaCantRef) {
                                $MovDetEnlace = new MovimientosDetRecord();
                                $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $MovDetDatos->Cantidad;
                                if ($Movimiento->IdConcepto == 177) {
                                    $diferenciaTotal = $MovDetEnlace->SubTotal - $MovDetDatos->SubTotal;
                                    if ($diferenciaTotal >= -2000 && $diferenciaTotal <= 2000 && $MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                                        $MovDetEnlace->Estado = 'CERRADO';
                                    }
                                } else if ($MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                                    $MovDetEnlace->Estado = 'CERRADO';
                                }
                                $MovDetEnlace->save();
                            } else if ($MovDetDatos->MovDet && $MovDetDatos->MovDet->IdDocumento == 75 && $MovDetDatos->IdDocumento != 19) {
                                $MovDetEnlace = new MovimientosDetRecord();
                                $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $MovDetDatos->Cantidad;
                                if ($MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                                    $MovDetEnlace->Estado = 'CERRADO';
                                }
                                $MovDetEnlace->save();
                            }
                            if ($MovDetEnlace) {
                                $strSql = "SELECT IdMovimientoDet FROM movimientos_det WHERE Estado <> 'CERRADO' AND IdMovimiento = " . $MovDetEnlace->IdMovimiento;
                                $arMovimientos = MovimientosDetRecord::finder()->findAllBySql($strSql);
                                if (count($arMovimientos) == 0) {
                                    $MovDatos = new MovimientosRecord();
                                    $MovDatos = MovimientosRecord::finder()->FindByPk($MovDetEnlace->IdMovimiento);
                                    $MovDatos->Estado = 'CERRADA';
                                    $MovDatos->save();
                                }
                            }
                        } else {
                            if ($MovDetDatos->MovDet != NULL || $MovDetDatos->MovDet != 0) {

                                if (($MovDetDatos->MovDet->Cantidad - $MovDetDatos->MovDet->CantAfectada) != 0) {
                                    $Cantidad = $MovDetDatos->Cantidad;
                                    if ($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion == 1) {
                                        $Cantidad = $MovDetDatos->MovDet->Cantidad;
                                    }

                                    if (($Cantidad) <= ($MovDetDatos->MovDet->Cantidad - $MovDetDatos->MovDet->CantAfectada) || $Movimiento->IdConcepto == 179) {


                                        $MovDetEnlace = new MovimientosDetRecord();
                                        $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                                        if ($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion != 1 || $Movimiento->IdConcepto == '') {
                                            $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $Cantidad;
                                            //Validamos las notas credito proveedor que afecte cantidad como costo.
                                            if ($MovDetEnlace->IdDocumento == 87 && $Movimiento->IdConcepto == 179) {
                                                if ($MovDetEnlace->Costo == $MovDetDatos->Costo && $MovDetEnlace->CantAfectada == $MovDetEnlace->Cantidad) {
                                                    $MovDetEnlace->Estado = 'CERRADO';
                                                }
                                            } else if ($MovDetEnlace->CantAfectada == $MovDetEnlace->Cantidad) {
                                                $MovDetEnlace->Estado = 'CERRADO';
                                            }
                                            $MovDetEnlace->save();
                                        }
                                        $Afectada = true;

                                        if ($MovDet->IdDocumento == 1) {
                                            $Item = ItemRecord::finder()->FindByPk($MovDet->Id_Item);
                                            $Item->CantOC = $Item->CantOC - $Cantidad;
                                            $Item->save();
                                        }
                                    } else {
                                        if ($MovDetEnlace->IdDocumento == 75) {
                                            
                                        }
                                        $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no esta disponible, ya fue enlazado anteriormente, revise por favor, o valide que la cantidad no sea mayor que la del documento inicial';
                                        $Afectada = false;
                                    }
                                } else {
                                    $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no esta disponible, ya fue enlazado anteriormente, revise por favor.';
                                    $Afectada = false;
                                }

                                if ($Afectada == true) {
                                    MovimientosRecord::CerrarAutoMov($MovDetEnlace->IdMovimiento);
                                }
                            } else {
                                $Afectada = true;
                            }
                        }
                    } else {
                        $Afectada = true;
                    }
                }
            }
            if ($Tipo == 2) {

                foreach ($MovDet as $MovDet) {

                    $MovDetDatos = new MovimientosDetRecord();
                    $MovDetDatos = MovimientosDetRecord::finder()->with_MovDet()->FindByPk($MovDet->IdMovimientoDet);
                    $Movimiento = new MovimientosRecord();
                    $Movimiento = MovimientosRecord::finder()->with_Concepto()->FindByPK($MovDet->IdMovimiento);

                    if ((($MovDetDatos->IdDocumento == 34 || $MovDetDatos->IdDocumento == 72) && $MovDetDatos->Operacion <> 0) || $MovDetDatos->IdDocumento == 36 || $MovDetDatos->TpDocumento == 33 || $MovDetDatos->IdDocumento == 10 || funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto)) {
                        $Afectada = true;
                        $Cantidad = $MovDetDatos->Cantidad;
                        $MovDetEnlace = new MovimientosDetRecord();
                        $MovDetEnlace = $MovDetDatos->MovDet ? MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet) : null;

                        if ($Movimiento->IdConcepto == 179 || $Movimiento->IdConcepto == 185) {

                            if ($Movimiento->IdConcepto == 185 && $Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion != 1 || $Movimiento->IdConcepto == '') {
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $Cantidad;
                                $MovDetEnlace->Estado = 'AUTORIZADO';
                                $MovDetEnlace->save();
                            } elseif ($Movimiento->IdConcepto == 179 && $MovDetEnlace->TpDocumento != 5) {
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $Cantidad;
                                $MovDetEnlace->Estado = 'AUTORIZADO';
                                $MovDetEnlace->save();
                            }
                        } else if (funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto) && $MovDetEnlace) {
                            $MovEnl = MovimientosRecord::finder()->with_DocumentoMov()->FindByPK($MovDetEnlace->IdMovimiento);
                            if ($MovEnl->DocumentoMov && $MovEnl->DocumentoMov->AfectaCantRef && $MovDetEnlace) {
                                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $Cantidad;
                                $MovDetEnlace->Estado = 'AUTORIZADO';
                                $MovDetEnlace->save();
                            }
                        }
                        $MovDetEnlace ? MovimientosRecord::CerrarAutoMov($MovDetEnlace->IdMovimiento) : null;
                    } else {

                        if ($MovDetDatos->MovDet != NULL || $MovDetDatos->MovDet != 0) {
                            if ($MovDetDatos->MovDet->CantAfectada > 0 || $MovDetDatos->TpDocumento == 33) {

                                if ($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion == 1) {
                                    $MovDetEnlace = new MovimientosDetRecord();
                                    $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);

                                    $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $MovDetEnlace->Cantidad;
                                    $MovDetEnlace->Estado = 'AUTORIZADO';
                                    $MovDetEnlace->save();
                                    $Afectada = true;
                                } else if (($MovDetDatos->Cantidad) <= ($MovDetDatos->MovDet->CantAfectada)) {

                                    $MovDetEnlace = new MovimientosDetRecord();
                                    $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);

                                    $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada - $MovDetDatos->Cantidad;
                                    $MovDetEnlace->Estado = 'AUTORIZADO';
                                    $MovDetEnlace->save();
                                    $Afectada = true;

                                    if ($MovDet->IdDocumento == 1) {
                                        $Item = ItemRecord::finder()->FindByPk($MovDet->Id_Item);
                                        $Item->CantOC = $Item->CantOC + $MovDetDatos->Cantidad;
                                        $Item->save();
                                    }
                                } else {
                                    $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no esta se puede devolver al pedido, ya fue enlazado anteriormente, revise por favor.';
                                    $Afectada = false;
                                }
                            } else {
                                $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no se puede devolver al pedido, ya fue enlazado anteriormente, revise por favor.';
                                $Afectada = false;
                            }



                            if ($Afectada == true) {
                                MovimientosRecord::CerrarAutoMov($MovDetDatos->MovDet->IdMovimiento);
                            }
                        } else {
                            $Afectada = true;
                        }
                    }
                }
            }



            if ($Afectada == true) {
                return true;
            } else {
                funciones::Mensaje($Mensaje, 2, $objVista);
                return false;
            }
        } else {
            return true;
        }*/
    }

    public static function registrarAfectadaAut($IdMovimiento, $objVista, $RegistrarAfectada, $Op, $MovDet) {
        $Afectada = true;
        try{
            foreach ($MovDet as $MovDet) {
                if ($Op == true && $MovDet->Estado != "AUTORIZADO" || $Op == false) {//Valida para que no vuelva a registrar un detalle que ya se autorizo.
                    $MovDetDatos = new MovimientosDetRecord();
                    $MovDetDatos = MovimientosDetRecord::finder()->with_MovDet()->FindByPk($MovDet->IdMovimientoDet);
                    $Movimiento = new MovimientosRecord();
                    $Movimiento = MovimientosRecord::finder()->with_Concepto()->with_DocumentoMov()->FindByPK($MovDet->IdMovimiento);
                    if (funciones::noValidaAfectadas($MovDetDatos, $Movimiento, $RegistrarAfectada)) {
                        $Afectada = funciones::validarDocumentoExcentosAfectada($Movimiento, $MovDetDatos);
                    } else {
                        $Afectada = funciones::validarDocumentoAfectada($IdMovimiento, $objVista, $RegistrarAfectada, $MovDet,$MovDetDatos,$Movimiento);
                    }
                } else {
                    $Afectada = true;
                }
            }
        }
        catch(Exception $e){
            $Afectada = false;
            funciones::Mensaje("Ocurrio un error ".$e, 2, $objVista);
        }
        
        return $Afectada;
    }

    public static function registrarAfectadaDesAut($IdMovimiento, $objVista, $RegistrarAfectada, $Op, $MovDet) {
        
    }

    public static function noValidaAfectadas($MovDetDatos, $Movimiento, $RegistrarAfectada) {
        return ((($MovDetDatos->IdDocumento == 34 || $MovDetDatos->IdDocumento == 72) && $MovDetDatos->Operacion <> 0) || funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto) || ($MovDetDatos->MovDet && $MovDetDatos->MovDet->IdDocumento == 75) || $RegistrarAfectada == false || $MovDetDatos->IdDocumento == 10);
    }

    public static function validarDocumentoExcentosAfectada($Movimiento, $MovDetDatos) {
        $Afectada = true;
        $MovDetEnlace = null;
        try {
            if ($Movimiento->IdConcepto == 179 && $Movimiento->IdDocumento == 87) {
                $Cantidad = $MovDetDatos->Cantidad;
                $MovDetEnlace = new MovimientosDetRecord();
                $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                if ($Movimiento->IdConcepto > 0) {
                    $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $Cantidad;
                    //Validamos las notas credito proveedor que afecte cantidad como costo.
                    if ($MovDetEnlace->IdDocumento == 85) {
                        $DifCostoRec = ($MovDetDatos->CantidadReconocidaNC * $MovDetDatos->DiferenciaCostoNC);
                        $DifSolicitada = ($MovDetDatos->CantSolicitud * $MovDetDatos->Costo);
                        $Diferencia = $DifCostoRec - $DifSolicitada;
                        if ($Diferencia >= -2000 && $Diferencia <= 2000 && $MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                            $MovDetEnlace->Estado = 'CERRADO';
                        }
                    } else if ($MovDetEnlace->CantAfectada <= $MovDetEnlace->Cantidad) {
                        $MovDetEnlace->Estado = 'CERRADO';
                    }
                    $MovDetEnlace->Comentarios = $MovDetDatos->Comentarios;
                    $MovDetEnlace->save();
                }
            } else if (funciones::conceptosNotacCreditoProv($Movimiento->IdConcepto) && $Movimiento->DocumentoMov->AfectaCantRef) {
                $MovDetEnlace = new MovimientosDetRecord();
                $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $MovDetDatos->Cantidad;
                if ($Movimiento->IdConcepto == 177) {
                    $diferenciaTotal = $MovDetEnlace->SubTotal - $MovDetDatos->SubTotal;
                    if ($diferenciaTotal >= -2000 && $diferenciaTotal <= 2000 && $MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                        $MovDetEnlace->Estado = 'CERRADO';
                    }
                } else if ($MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                    $MovDetEnlace->Estado = 'CERRADO';
                }
                $MovDetEnlace->save();
            } else if ($MovDetDatos->MovDet && $MovDetDatos->MovDet->IdDocumento == 75 && $MovDetDatos->IdDocumento != 19) {
                $MovDetEnlace = new MovimientosDetRecord();
                $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $MovDetDatos->Cantidad;
                if ($MovDetEnlace->CantAfectada >= $MovDetEnlace->Cantidad) {
                    $MovDetEnlace->Estado = 'CERRADO';
                }
                $MovDetEnlace->save();
            }
            if ($MovDetEnlace) {
                $strSql = "SELECT IdMovimientoDet FROM movimientos_det WHERE Estado <> 'CERRADO' AND IdMovimiento = " . $MovDetEnlace->IdMovimiento;
                $arMovimientos = MovimientosDetRecord::finder()->findAllBySql($strSql);
                if (count($arMovimientos) == 0) {
                    $MovDatos = new MovimientosRecord();
                    $MovDatos = MovimientosRecord::finder()->FindByPk($MovDetEnlace->IdMovimiento);
                    $MovDatos->Estado = 'CERRADA';
                    $MovDatos->save();
                }
            }
            return true;
        } catch (Exception $e) {
            funciones::Mensaje('Ocurrio un error ' . $e, 2, $this);
            return false;
        }
    }

    public static function validarDocumentoAfectada($IdMovimiento, $objVista, $RegistrarAfectada, $MovDet,$MovDetDatos,$Movimiento) {
        $Afectada = true;
        try {
            if ($MovDetDatos->MovDet != NULL || $MovDetDatos->MovDet != 0) {
                if (($MovDetDatos->MovDet->Cantidad - $MovDetDatos->MovDet->CantAfectada) != 0) {
                    $Cantidad = $MovDetDatos->Cantidad;
                    if ($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion == 1) {
                        $Cantidad = $MovDetDatos->MovDet->Cantidad;
                    }

                    if (($Cantidad) <= ($MovDetDatos->MovDet->Cantidad - $MovDetDatos->MovDet->CantAfectada) || $Movimiento->IdConcepto == 179) {


                        $MovDetEnlace = new MovimientosDetRecord();
                        $MovDetEnlace = MovimientosDetRecord::finder()->FindByPk($MovDetDatos->MovDet->IdMovimientoDet);
                        if ($Movimiento->IdConcepto > 0 && $Movimiento->Concepto->Opcion != 1 || $Movimiento->IdConcepto == '') {
                            $MovDetEnlace->CantAfectada = $MovDetEnlace->CantAfectada + $Cantidad;
                            //Validamos las notas credito proveedor que afecte cantidad como costo.
                            if ($MovDetEnlace->IdDocumento == 87 && $Movimiento->IdConcepto == 179) {
                                if ($MovDetEnlace->Costo == $MovDetDatos->Costo && $MovDetEnlace->CantAfectada == $MovDetEnlace->Cantidad) {
                                    $MovDetEnlace->Estado = 'CERRADO';
                                }
                            } else if ($MovDetEnlace->CantAfectada == $MovDetEnlace->Cantidad) {
                                $MovDetEnlace->Estado = 'CERRADO';
                            }
                            $MovDetEnlace->save();
                        }
                        $Afectada = true;

                        if ($MovDet->IdDocumento == 1) {
                            $Item = ItemRecord::finder()->FindByPk($MovDet->Id_Item);
                            $Item->CantOC = $Item->CantOC - $Cantidad;
                            $Item->save();
                        }
                    } else {
                        if ($MovDetEnlace->IdDocumento == 75) {
                            
                        }
                        $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no esta disponible, ya fue enlazado anteriormente, revise por favor, o valide que la cantidad no sea mayor que la del documento inicial';
                        $Afectada = false;
                    }
                } else {
                    $Mensaje = 'La cantidad ingresada en el item ' . $MovDetDatos->Id_Item . ' no esta disponible, ya fue enlazado anteriormente, revise por favor.';
                    $Afectada = false;
                }

                if ($Afectada == true) {
                    MovimientosRecord::CerrarAutoMov($MovDetEnlace->IdMovimiento);
                }
            } else {
                $Afectada = true;
            }
            funciones::Mensaje($Mensaje, 2, $objVista);
            return $Afectada;
        } catch (Exception $e) {
            funciones::Mensaje('Ocurrio un error '.$e, 2, $objVista);
            return false;
        }
    }

}

?>
