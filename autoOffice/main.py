import xlrd


class AutoOffice:
    def __init__(self):
        self.serial_key_list={}
        with open("./resource/serial_key.txt","r") as fp:
            ret = fp.read()
            self.serial_key_list = ret.split(",")
        
        self.data = xlrd.open_workbook("./resource/qf.xls")

    def get_prod_serial(self):
        pass

ao =AutoOffice().serial_key_list



