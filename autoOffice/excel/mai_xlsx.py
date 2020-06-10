import xlrd
import os
import logging
from xlutils.copy import copy


class AutoOffice:
    def __init__(self):
        logging.basicConfig(level=logging.INFO,
                            format='%(asctime)s %(levelname)s %(message)s', )
        logging.info("Now initializing..")
        self.target_excel_files = []
        self.failed_excel_files = []
        self.target_cols = None
        logging.info("Reading serial key words..")
        with open("../csv_autor/serial_key.txt", "r") as fp:
            ret = fp.read()

            self.serial_key_list = ret.split(",")
        logging.info("Reading excel files..")
        for file in os.listdir("../excelFiles"):
            if file.endswith(".xls") or file.endswith("xlsx"):
                self.target_excel_files.append(file)
        logging.info("Initialization succeed!")

    def excel_process(self, file) -> bool:
        try:
            data = xlrd.open_workbook("./excelFiles/" + file, formatting_info=True)
            sheet = data.sheets()[0]
            table_rows_nums = sheet.nrows
            table_cols_nums = sheet.ncols
            new_excel = copy(data)
            sheet_writer = new_excel.get_sheet(0)
            sheet_writer.write(0, table_cols_nums + 1, "产品系列")
            for col in range(table_cols_nums):
                if sheet.cell(0, col).value == "宝贝标题":
                    self.target_cols = col
            if self.target_cols is None:
                return False
            for row in range(table_rows_nums):
                for key in self.serial_key_list:
                    if key in str(sheet.cell(row, self.target_cols).value):
                        sheet_writer.write(row, table_cols_nums + 1, key)
            try:
                new_excel.save("./results/{}".format(file))
            except Exception as e:
                print(e)
            return True
        except Exception as e:
            self.failed_excel_files.append(file)
            logging.info("{}'s process failed, the error is: {}".format(file, e))
            return False

    def start(self):
        logging.info("Begin to process excel files..")
        for excel in self.target_excel_files:
            logging.info("Now processing {}".format(excel))
            if self.excel_process(excel):
                logging.info("{}'s process succeed!".format(excel))
        logging.info("Process finished")
        if self.failed_excel_files:
            logging.info("Failed files are: {}".format(self.failed_excel_files))


if __name__ == '__main__':
    ao = AutoOffice()
    ao.start()
