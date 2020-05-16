import os
import logging


class AutoOffice:
    def __init__(self):
        logging.basicConfig(level=logging.INFO,
                            format='%(asctime)s %(levelname)s %(message)s', )
        logging.info("Now initializing..")
        self.target_csv_files = []
        self.failed_csv_files = []
        self.target_cols = None
        self.ret_list = []
        logging.info("Reading serial key words..")
        with open("./serial_key.txt", "r") as fp:
            ret = fp.read()
            self.serial_key_list = ret.split(",")
        self.serial_key_list.sort(reverse=True)
        logging.info("Reading excel files..")
        for file in os.listdir("./raw_files"):
            if file.endswith(".csv"):
                self.target_csv_files.append(file)
        logging.info("Initialization succeed!")
    
    def switch_to_lists(self, lists: str):
        lists = lists.split(",")
        lists = [x.replace("\n", "") for x in lists]
        return lists

    def csv_process(self, file) -> bool:
        try:
            with open("./raw_files/" + file, "r+") as fp:
                col = fp.readline()
                col_list = self.switch_to_lists(col)
                if "产品系列" not in col_list:
                    col_list.append("产品系列\n")
                for i, v in enumerate(col_list):
                    if v == "宝贝标题":
                        self.target_cols = i
                if self.target_cols is None:
                    return False
                [self.ret_list.append(i) for i in col_list]
                for row in fp.readlines():
                    switch_row = self.switch_to_lists(row)
                    [self.ret_list.append(i) for i in switch_row]
                    for key in self.serial_key_list:
                        if key in switch_row[self.target_cols]:
                            self.ret_list.append(key)
                            break
                    self.ret_list.append("\n")
            with open("./results/" + file, "w") as fp:
                for i in self.ret_list:
                    fp.write(i)
                    if "\n" not in i:
                        fp.write(",")
        except Exception as e:
            self.failed_csv_files.append(file)
            logging.info("{}'s process failed, the error is: {}".format(file, e))
            return False

    def start(self):
        logging.info("Begin to process excel files..")
        for excel in self.target_csv_files:
            self.ret_list = []
            logging.info("Now processing {}".format(excel))
            if self.csv_process(excel):
                logging.info("{}'s process succeed!".format(excel))
        logging.info("Process finished")
        if self.failed_csv_files:
            logging.info("Failed files are: {}".format(self.failed_csv_files))


if __name__ == '__main__':
    ao = AutoOffice()
    ao.start()
